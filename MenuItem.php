<?php
/**
 * DokuWiki Plugin tododone (Menu Item)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 */

namespace dokuwiki\plugin\tododone;

use dokuwiki\Menu\Item\AbstractItem;

class MenuItem extends AbstractItem
{
    /** @var string DokuWiki do action */
    protected $type = 'movetaggedtodos';

    /**
     * MenuItem constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->label = 'Move tagged todos';
        $this->title = 'Move <todo #tag> items to ## done';
        $this->params['sectok'] = \getSecurityToken();
        $this->svg = __DIR__ . '/todo.svg';
    }
}
