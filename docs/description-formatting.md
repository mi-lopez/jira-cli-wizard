# Description Formatting

`jira-wizard` converts simple Markdown-style descriptions to Jira Atlassian Document Format (ADF) before creating issues.

Supported formatting:

- `**bold text**` becomes Jira bold text.
- Lines starting with `- ` or `* ` become Jira bullet lists.
- Blank lines separate paragraphs and list blocks.

Example input:

```md
**Description**
Explain the issue.

**Expected result**
- First validation point.
- Second validation point with **bold** text.
```

Expected Jira result:

- Section labels render as bold text.
- Bullets render as actual Jira bullet lists.
- Literal `**` markers are not shown in the Jira Description field.

This behavior is used by both:

- `jira-wizard create`
- `jira-wizard create-from`
