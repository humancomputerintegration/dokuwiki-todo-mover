# Todo Done Mover for DokuWiki

Adds a page action named **Move tagged todos**. When clicked, it rewrites the current page source by moving whole-line todo items whose opening tag contains a hashtag token to a bottom `## done` section.

## Example

Before:

```text
<todo>first thing</todo>
<todo #plopes>second thing</todo>
<todo due:monday>third thing</todo>
```

After clicking **Move tagged todos**:

```text
<todo>first thing</todo>
<todo due:monday>third thing</todo>

## done
<todo #plopes>second thing</todo>
```

## Install

Copy this folder to:

```text
lib/plugins/tododone/
```

Then visit a DokuWiki page where you have edit permission and click **Move tagged todos** in the page tools/menu.

## Notes

- It only moves whole-line todo items, optionally preceded by a simple list marker such as `* ` or `- `.
- It detects a hashtag token in the opening tag, e.g. `<todo #done>...</todo>`.
- It does not move `<todo due:monday>...</todo>`.
- It intentionally requires DokuWiki edit permission and a security token.
- DokuWiki's native heading syntax is equals-sign based, not Markdown `##`. This plugin writes `## done` because that is the requested output. If your wiki does not use Markdown-style headings, change `DONE_HEADING` in `action.php` to something like `===== done =====` and adjust `finalLevelTwoSectionIsDone()` accordingly.
