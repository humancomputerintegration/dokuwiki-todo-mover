<?php
/**
 * DokuWiki Plugin todomover (Action Component)
 *
 * Adds a page action that moves todo items with a hashtag in the opening
 * <todo ...> tag to a final "## done" section.
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 */

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;

if (!defined('DOKU_INC')) die();

class action_plugin_todomover extends ActionPlugin
{
    const ACTION = 'movetaggedtodos';
    const DONE_HEADING = '## done';

    /**
     * Register DokuWiki event hooks.
     *
     * @param EventHandler $controller
     * @return void
     */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handleAction');
        $controller->register_hook('MENU_ITEMS_ASSEMBLY', 'AFTER', $this, 'addMenuButton');
        $controller->register_hook('TEMPLATE_PAGETOOLS_DISPLAY', 'BEFORE', $this, 'addLegacyButton');
    }

    /**
     * Add the action to modern page menus.
     *
     * @param Event $event
     * @param mixed $param
     * @return void
     */
    public function addMenuButton(Event $event, $param)
    {
        global $ID, $REV;

        if (($event->data['view'] ?? '') !== 'page') return;
        if ($REV) return;
        if (auth_quickaclcheck($ID) < AUTH_EDIT) return;

        try {
            array_splice($event->data['items'], 1, 0, [new \dokuwiki\plugin\todomover\MenuItem()]);
        } catch (\Exception $ignored) {
            // Some templates/menu configurations may reject custom items. The legacy
            // pagetools hook below is kept as a fallback.
        }
    }

    /**
     * Add the action to legacy page tool menus.
     *
     * @param Event $event
     * @param mixed $param
     * @return void
     */
    public function addLegacyButton(Event $event, $param)
    {
        global $ID, $REV;

        if (($event->data['view'] ?? '') !== 'main') return;
        if ($REV) return;
        if (auth_quickaclcheck($ID) < AUTH_EDIT) return;
        if (!isset($event->data['items']) || !is_array($event->data['items'])) return;

        $url = wl($ID, ['do' => self::ACTION, 'sectok' => getSecurityToken()], false, '&');
        $event->data['items']['movetaggedtodos'] =
            '<li><a href="' . hsc($url) . '" class="action movetaggedtodos" rel="nofollow">' .
            '<span>Move tagged todos</span></a></li>';
    }

    /**
     * Handle ?do=movetaggedtodos.
     *
     * @param Event $event
     * @param mixed $param
     * @return void
     */
    public function handleAction(Event $event, $param)
    {
        global $ID, $REV;

        if ($event->data !== self::ACTION) return;

        $event->preventDefault();
        $event->stopPropagation();

        if ($REV) {
            msg('Todo Done Mover cannot modify an old page revision.', -1);
            $this->redirectToPage();
        }

        if (!checkSecurityToken()) {
            $this->redirectToPage();
        }

        $info = pageinfo();
        if (empty($info['exists'])) {
            msg('Todo Done Mover: page does not exist.', -1);
            $this->redirectToPage();
        }

        if (empty($info['editable']) || auth_quickaclcheck($ID) < AUTH_EDIT) {
            msg('Todo Done Mover: you do not have permission to edit this page, or it is locked.', -1);
            $this->redirectToPage();
        }

        $oldText = io_readWikiPage(wikiFN($ID), $ID);
        list($newText, $movedCount) = $this->moveTaggedTodos($oldText);

        if ($movedCount === 0 || $newText === $oldText) {
            msg('Todo Done Mover: no <todo #tag> items found to move.', 0);
            $this->redirectToPage();
        }

        saveWikiText(
            $ID,
            $newText,
            'Moved ' . $movedCount . ' tagged todo item(s) to ' . self::DONE_HEADING,
            true
        );

        msg('Todo Done Mover: moved ' . $movedCount . ' tagged todo item(s) to ' . self::DONE_HEADING . '.', 1);
        $this->redirectToPage();
    }

    /**
     * Move whole-line todo items whose opening tag contains a whitespace-prefixed hashtag.
     *
     * Examples moved:
     *   <todo #plopes>a done today to move</todo>
     *
     * Examples not moved:
     *   <todo>not done todo</todo>
     *   <todo due:monday>also not done</todo>
     *
     * @param string $text Raw page source
     * @return array [newText, movedCount]
     */
    public function moveTaggedTodos($text)
    {
        $eol = (strpos($text, "\r\n") !== false) ? "\r\n" : "\n";
        $work = str_replace(["\r\n", "\r"], "\n", $text);
        $moved = [];

        // Whole-line todo blocks only, with optional bullet marker. The hashtag must
        // appear inside the opening <todo ...> tag as a token, for example " #done".
        $pattern = '/^[^\S\n]*(?:[-*]\s+)?<todo\b(?=[^>\n]*(?<!\S)#[A-Za-z0-9][A-Za-z0-9_-]*)[^>\n]*>.*?<\/todo>[^\S\n]*(?:\n|$)/ims';

        $remaining = preg_replace_callback($pattern, function ($match) use (&$moved) {
            $item = trim($match[0], "\n");
            $item = rtrim($item, " \t\n");
            if (trim($item) !== '') {
                $moved[] = $item;
            }
            return '';
        }, $work);

        if (count($moved) === 0) {
            return [$text, 0];
        }

        $remaining = rtrim($remaining, "\n");

        // If the final Markdown-style level-2 section is not already "## done",
        // create a new bottom section. This keeps the moved todos at page bottom.
        if (!$this->finalLevelTwoSectionIsDone($remaining)) {
            if ($remaining !== '') {
                $remaining .= "\n\n";
            }
            $remaining .= self::DONE_HEADING;
        }

        if ($remaining !== '' && substr($remaining, -1) !== "\n") {
            $remaining .= "\n";
        }
        $remaining .= implode("\n", $moved) . "\n";

        return [str_replace("\n", $eol, $remaining), count($moved)];
    }

    /**
     * Return true when the last Markdown-style level-2 section is exactly "## done".
     *
     * @param string $text Normalized source using \n line endings
     * @return bool
     */
    private function finalLevelTwoSectionIsDone($text)
    {
        if (!preg_match_all('/^##[ \t]+(.+?)[ \t]*$/im', $text, $matches, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        $lastIndex = count($matches[1]) - 1;
        $lastHeadingText = strtolower(trim($matches[1][$lastIndex][0]));

        return $lastHeadingText === 'done';
    }

    /**
     * Redirect back to the current wiki page and stop request processing.
     *
     * @return void
     */
    private function redirectToPage()
    {
        global $ID;
        send_redirect(wl($ID, '', false, '&'));
        exit;
    }
}
