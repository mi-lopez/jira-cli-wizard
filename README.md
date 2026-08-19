# 🎯 Jira CLI Wizard

[![CI](https://github.com/mi-lopez/jira-cli-wizard/actions/workflows/ci.yml/badge.svg)](https://github.com/mi-lopez/jira-cli-wizard/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![Latest Version](https://img.shields.io/badge/version-1.0.0-orange)](https://github.com/mi-lopez/jira-cli-wizard/releases)

A beautiful, interactive CLI wizard for creating Jira tickets with smart defaults and an intuitive user experience. Skip the web interface hassle and create tickets directly from your terminal!

## ✨ Features

- 🧙‍♂️ **Interactive Wizard**: Step-by-step guided ticket creation
- 🤖 **Non-Interactive Mode**: Create tickets from scripts and AI agents using flags
- 🎯 **Smart Defaults**: Suggests active sprints, recent epics, and assignees
- 🚀 **Quick Creation**: Create tickets based on existing ones
- ✏️ **Update Command**: Modify fields on existing tickets, interactively or by flag
- 🔀 **Workflow Transitions**: Move a ticket's status by name, and list the moves that are legal right now
- 👀 **View Command**: Read a ticket and its comments in the terminal, with the text back in Markdown
- 📎 **Attachments**: Upload files and screenshots on create, create-from and update
- 📝 **Markdown Descriptions**: Headings, lists, bold, italic, code and links render as real Jira ADF
- 🏷️ **Labels**: Prompted in the wizard, prefilled from the template when copying a ticket
- 🔄 **Template System**: Copy settings from existing tickets
- 🔍 **Resource Discovery**: List projects, issue types, priorities, epics, sprints and transitions as JSON
- 🧪 **Dry Run**: Preview the full payload before creating any ticket
- 🔒 **Secure**: API token-based authentication
- 🎨 **Beautiful UI**: Colorful, user-friendly terminal interface
- ⚡ **Fast Setup**: One-command configuration
- 📋 **Complete Support**: Projects, issue types, priorities, assignees, sprints, and epics
- 🔗 **Epic Linking**: Automatically link tickets to epics
- 🏃 **Sprint Integration**: Add tickets to active sprints
- 📊 **Status Monitoring**: Check configuration and connection status
- 🔍 **Smart Search**: Find projects, users, and issue types by name or partial match

## 🚀 Quick Start

### Installation

```bash
composer require mi-lopez/jira-cli-wizard --dev
```

### Configuration

Configure your Jira credentials (one-time setup):

```bash
./vendor/bin/jira-wizard configure
```

You'll need:
- Your Jira instance URL (e.g., `https://yourcompany.atlassian.net`)
- Your email address
- An API token ([generate here](https://id.atlassian.com/manage-profile/security/api-tokens))

### Create Your First Ticket

```bash
./vendor/bin/jira-wizard create
```

The wizard will guide you through:
1. **Project Selection** - Choose from your accessible projects
2. **Issue Type** - Select Story, Bug, Task, etc.
3. **Summary** - Enter a descriptive title
4. **Description** - Add details (optional)
5. **Priority** - Set importance level
6. **Assignee** - Assign to team members or leave unassigned
7. **Additional Options** - Link to epics, add to active sprint

## 🔥 Power Features

### Create from Existing Ticket

The fastest way to create similar tickets:

```bash
# Copy all settings from an existing ticket
./vendor/bin/jira-wizard create-from CAM-1106

# Copy to a different project
./vendor/bin/jira-wizard create-from CAM-1106 --project TRIGB2B
```

**What gets copied:**
- ✅ Project (or override with `--project`)
- ✅ Issue type
- ✅ Priority
- ✅ Assignee
- ✅ Epic (if linked)
- ✅ Sprint (current active sprint)

**What you provide:**
- ✅ New summary
- ✅ New description
- ✅ Optional: modify any copied settings

## 📖 Usage Examples

### Standard Ticket Creation

```bash
$ ./vendor/bin/jira-wizard create

🎯 Jira CLI Wizard
==================

✅ Connected to Jira successfully!

[1/7] Select Project
--------------------
Available projects:
  [0] PROJ - My Project
  [1] DEV - Development Team
  [2] CAM - CAMEL NETWORKS - OroCommerce project
> 2

[2/7] Select Issue Type
-----------------------
Available issue types:
  [0] Tarea - Un trabajo pequeño e independiente
  [1] Historia - Una función o funcionalidad expresada como objetivo del usuario
  [2] Error - Un problema o error
> 1

[3/7] Enter Summary
-------------------
Enter ticket summary: Implement user authentication system

[4/7] Enter Description
-----------------------
Enter description (optional): Add JWT-based authentication with login/logout functionality

[5/7] Select Priority
---------------------
Available priorities:
  [skip] Skip (use default)
  [0] Highest
  [1] High
  [2] Medium
> 1

[6/7] Select Assignee
--------------------
Available assignees:
  [unassigned] Unassigned
  [0] John Doe
  [1] Jane Smith
  [2] Miguel Lopez
> 2

[7/7] Additional Options
-----------------------
🏃 Sprint Options
Add to active sprint 'Sprint 23'? (y/N): y
✅ Will add to sprint: Sprint 23

📚 Epic Options
Select epic (optional):
  [skip] Skip (no epic)
  [0] CAM-1037 - Oro 6.1 Upgrade
  [1] CAM-761 - MVP3 Distributor Quote
> 0

📋 Ticket Summary
================
📁 Project: CAM - CAMEL NETWORKS - OroCommerce project
🎯 Type: Historia
📝 Summary: Implement user authentication system
📄 Description: Add JWT-based authentication with login/logout functionality
⚡ Priority: High
👤 Assignee: Miguel Lopez
🏃 Sprint: Sprint 23
📚 Epic: CAM-1037

🤔 Create this ticket? (y/N): y

🚀 Creating ticket...
✅ Ticket created successfully!
📝 Issue Key: CAM-1107
🔗 URL: https://yourcompany.atlassian.net/browse/CAM-1107
📋 Adding to sprint...
✅ Added to sprint!
```

### Quick Template Creation

```bash
$ ./vendor/bin/jira-wizard create-from CAM-1106

📋 Original Ticket Template
============================
📁 Project: CAM - CAMEL NETWORKS - OroCommerce project
🎯 Type: Tarea
📝 Summary: Test from API
⚡ Priority: Medium
👤 Assignee: Miguel Lopez
🏃 Sprint: CAMEL Sprint 18
📚 Epic: CAM-1037 - Oro 6.1 Upgrade

🎯 Create New Ticket
====================
📁 Project: CAM - CAMEL NETWORKS - OroCommerce project

📝 Enter new ticket summary: Fix login validation bug
📄 Enter description (optional): Users cannot login with special characters in password
🔧 Do you want to modify the copied settings? (y/N): n

📋 New Ticket Summary
=====================
📁 Project: CAM - CAMEL NETWORKS - OroCommerce project
🎯 Type: Tarea
📝 Summary: Fix login validation bug
📄 Description: Users cannot login with special characters in password
⚡ Priority: Medium
👤 Assignee: Miguel Lopez
🏃 Sprint: CAMEL Sprint 18
📚 Epic: CAM-1037

🤔 Create this ticket? (y/N): y

🚀 Creating ticket...
✅ Ticket created successfully!
📝 Issue Key: CAM-1108
🔗 URL: https://yourcompany.atlassian.net/browse/CAM-1108
📋 Adding to sprint...
✅ Added to sprint!
```

### Cross-Project Template

```bash
$ ./vendor/bin/jira-wizard create-from CAM-1106 --project TRIGB2B

📋 Original Ticket Template
============================
📁 Project: CAM - CAMEL NETWORKS - OroCommerce project
🎯 Type: Tarea
📝 Summary: Test from API
⚡ Priority: Medium
👤 Assignee: Miguel Lopez

🎯 Create New Ticket
====================
📁 Project: TRIGB2B - TRIGANO - B2B - Projet OroCommerce

📝 Enter new ticket summary: Implement similar API feature
📄 Enter description (optional): Port the API functionality to TRIGB2B project
🔧 Do you want to modify the copied settings? (y/N): y

⚡ Change priority? (y/N): y
Available priorities:
  [skip] Skip (use default)
  [0] Highest
  [1] High
  [2] Medium
> 1

👤 Change assignee? (y/N): n

🚀 Creating ticket...
✅ Ticket created successfully!
📝 Issue Key: TRIGB2B-456
```

## 🛠️ Available Commands

### Create Ticket (Interactive)
```bash
./vendor/bin/jira-wizard create
# or simply:
./vendor/bin/jira-wizard
```
Full interactive wizard for creating tickets from scratch.

### Create Ticket (Non-Interactive)

Skip the wizard entirely by passing flags. Outputs only the issue key to stdout — ideal for scripts and AI agents.

```bash
./vendor/bin/jira-wizard create \
  --project=ALDO \
  --type=Task \
  --summary="Upgrade bundle X for OroCommerce 6.1" \
  --description="Check breaking changes in the CHANGELOG" \
  --epic=ALDO-526 \
  --labels=upgrade,orocommerce \
  --priority=High \
  --sprint=active

# stdout: ALDO-123
```

**Available flags:**

| Flag | Short | Required | Description |
|------|-------|----------|-------------|
| `--project` | `-p` | Yes | Project key (e.g. `ALDO`) |
| `--type` | `-t` | Yes | Issue type name (e.g. `Task`, `Story`, `Epic`) |
| `--summary` | `-s` | Yes | Ticket title |
| `--description` | `-d` | No | Ticket description |
| `--parent` | | No | Parent/epic key (e.g. `ALDO-10`) |
| `--epic` | | No | Alias for `--parent` |
| `--labels` | `-l` | No | Comma-separated labels (e.g. `upgrade,backend`) |
| `--priority` | | No | Priority name (e.g. `High`, `Medium`, `Low`) |
| `--sprint` | | No | Sprint ID or `active` to auto-resolve the current sprint |
| `--assignee` | `-a` | No | Display name, email, account id, or `me` |
| `--attachment` | | No | Path to a file to upload. Repeatable |
| `--dry-run` | | No | Print the JSON payload without creating the ticket |

> `--assignee` resolves by exact display name or email first, then by partial match. An ambiguous
> partial match is rejected with the list of candidates rather than silently picking one.
>
> `--dry-run` requires `--project`, `--type` and `--summary`. Without them it fails instead of
> falling through to the interactive wizard, which would create a real ticket.

**Capture the key in a script:**
```bash
KEY=$(./vendor/bin/jira-wizard create --project=ALDO --type=Task --summary="..." --no-interaction)
echo "Created: $KEY"
```

**Preview before creating (dry-run):**
```bash
./vendor/bin/jira-wizard create \
  --project=ALDO --type=Task --summary="Test" --sprint=active --dry-run
```

### Update an Existing Ticket

```bash
# Non-interactive: only the fields you pass are touched
./vendor/bin/jira-wizard update ALDO-123 \
  --summary="New title" \
  --description="## Context\n\n- point one\n- point two" \
  --priority=High \
  --assignee=me \
  --labels=backend,upgrade \
  --sprint=active \
  --status="En cours"

# Interactive: current values are offered as defaults
./vendor/bin/jira-wizard update ALDO-123

# Preview without writing
./vendor/bin/jira-wizard update ALDO-123 --summary="New title" --dry-run
```

Fields accept the same values as `create`. Two update-specific conventions:

- `--status` moves the ticket through the workflow. Status is not a writable field in Jira,
  so this rides `POST /issue/{key}/transitions` instead of the field update. It accepts a
  transition id, a transition name, or the name of the resulting status, matched
  case-insensitively; an unambiguous fragment works too. When nothing matches, the error
  lists what is legal from the current status. Asking for the status the ticket already has
  is a no-op, not a failure, so re-running a script is safe.
- `--assignee=unassigned` (or `none`) clears the assignee.
- `--epic=none` clears the parent link.
- An empty `--labels=` clears every label, whereas omitting the flag leaves them untouched.

### View a Ticket

```bash
./vendor/bin/jira-wizard view ALDO-123

# Keys are case-insensitive
./vendor/bin/jira-wizard view aldo-123

# Include the comment thread
./vendor/bin/jira-wizard view ALDO-123 --comments

# Flattened summary for scripts and AI agents
./vendor/bin/jira-wizard view ALDO-123 --json
```

```text
ALDO-123 Checkout crashes on retry
────────────────────────────────────────────────────────────
  Status     In Progress
  Type       Bug
  Priority   High
  Assignee   Ada Lovelace
  Reporter   Alan Turing
  Project    ALDO
  Parent     ALDO-9 — Checkout
  Labels     backend, urgent
  Created    2026-08-01 10:22
  Updated    2026-08-18 16:40
  URL        https://your-domain.atlassian.net/browse/ALDO-123

Description

## Steps

1. Add an item to the cart
2. Retry the payment
```

The description comes back from Jira as ADF and is rendered in the same Markdown flavour
`create` and `update` accept, so a ticket can be read, edited and sent back without the
formatting drifting. Headings, lists, task lists, tables, code blocks, quotes, mentions and
links are all preserved; attachments show as `[attachment: name]`.

`--comments` (`-c`) appends the thread, oldest first, with author, date and an `(edited …)`
marker when the comment was actually changed. Comments live behind their own endpoint, so
they cost an extra request and are only fetched when you ask for them:

```text
Comments (2)

  Victor Donoso · 2026-04-09 18:00
  No se necesitan cambios, validado hoy con Marina.

  Ada Lovelace · 2026-04-10 09:12 (edited 2026-04-10 09:30)
  Reopened: it still fails on **staging**.
```

`--json` prints a flattened object — key, url, summary, status, type, priority, assignee,
reporter, project, parent, labels, timestamps and the Markdown description — rather than
Jira's raw payload, which also carries the changelog and every rendered field. With
`--comments` it also carries a `comments` array (author, created, updated, Markdown body);
without the flag the key is absent, which is a different claim from an empty list.

### Attach Files

Works on `create`, `create-from` and `update`. The flag is repeatable and the wizard also
prompts for files:

```bash
./vendor/bin/jira-wizard create -p ALDO -t Bug -s "Checkout crash" \
  --attachment=screenshot.png \
  --attachment=stacktrace.log
```

Missing files are reported before anything is sent to Jira.

### Markdown in Descriptions

Descriptions are converted to Jira's ADF, so markdown renders as real formatting instead of
literal characters:

```bash
./vendor/bin/jira-wizard create -p ALDO -t Task -s "Upgrade" -d "$(cat <<'EOF'
# Goal

Upgrade the bundle to **6.1**.

- Review the `CHANGELOG`
- Check [the docs](https://example.com)

1. Bump the constraint
2. Run the suite
EOF
)"
```

Supported: headings, bullet and ordered lists, `**bold**`, `*italic*`, `` `code` ``,
`[links](url)`, blank lines as paragraph breaks and single newlines as hard breaks.

### List Resources as JSON

Discover valid values for flags before scripting or using with an AI agent:

```bash
# Show available resource types
./vendor/bin/jira-wizard list

# List all projects
./vendor/bin/jira-wizard list projects

# List issue types for a project
./vendor/bin/jira-wizard list issue-types --project=ALDO

# List priorities
./vendor/bin/jira-wizard list priorities

# List epics for a project
./vendor/bin/jira-wizard list epics --project=ALDO

# List active sprint for a project
./vendor/bin/jira-wizard list sprints --project=ALDO

# List the transitions available on a ticket right now
./vendor/bin/jira-wizard list transitions --issue=ALDO-123
```

Transitions are keyed off the issue, not the project: which moves are legal depends on
where the ticket currently stands, so the list never offers a step Jira would reject.

```json
[
  {"id": "2",   "name": "En cours de traitement", "to": "En cours de traitement"},
  {"id": "271", "name": "Annulé",                 "to": "Annulé"}
]
```

**Example output (`list projects`):**
```json
[
  {"key": "ALDO", "name": "ALDO - B2B project"},
  {"key": "SYN",  "name": "Synoproject"}
]
```

**Typical AI agent workflow:**
```bash
# 1. Discover project key
PROJECT=$(./vendor/bin/jira-wizard list projects | jq -r '.[] | select(.name | test("ALDO")) | .key')

# 2. Discover epic key
EPIC=$(./vendor/bin/jira-wizard list epics --project=$PROJECT | jq -r '.[0].key')

# 3. Create ticket
KEY=$(./vendor/bin/jira-wizard create \
  --project=$PROJECT --type=Task \
  --summary="Automated task" --epic=$EPIC --sprint=active \
  --no-interaction)

echo "Created: $KEY"
```

### Create from Template
```bash
./vendor/bin/jira-wizard create-from <ISSUE-KEY>
```
Create a new ticket using an existing ticket as a template.

**Examples:**
```bash
# Copy all settings from CAM-1106
./vendor/bin/jira-wizard create-from CAM-1106

# Copy settings but create in different project
./vendor/bin/jira-wizard create-from CAM-1106 --project TRIGB2B

# Quick bug fix based on existing bug
./vendor/bin/jira-wizard create-from PROJ-123
```

### View a Ticket

```bash
./vendor/bin/jira-wizard view <ISSUE-KEY> [--comments] [--json]
```

**Examples:**
```bash
# Read a ticket in the terminal
./vendor/bin/jira-wizard view ALDO-123

# Read it with its comment thread
./vendor/bin/jira-wizard view ALDO-123 -c

# Pipe the ticket into another tool
./vendor/bin/jira-wizard view ALDO-123 --json | jq -r .description
```

### Configure Credentials
```bash
./vendor/bin/jira-wizard configure
```
Set up or update your Jira credentials and connection settings.

### Check Status
```bash
./vendor/bin/jira-wizard status
```
Display current configuration and test connection to Jira.

### Get Help
```bash
./vendor/bin/jira-wizard --help
./vendor/bin/jira-wizard list --help
./vendor/bin/jira-wizard create-from --help
```

## ⚙️ Configuration

Configuration is stored in `~/.jira-cli-config.json`. You can:

- **View current config**: `./vendor/bin/jira-wizard status`
- **Reconfigure**: `./vendor/bin/jira-wizard configure`
- **Manual edit**: Edit `~/.jira-cli-config.json` directly

### Environment Variables

You can also set configuration via environment variables:

```bash
export JIRA_URL="https://yourcompany.atlassian.net"
export JIRA_EMAIL="your.email@company.com"
export JIRA_TOKEN="your-api-token"
```

## 🎯 Smart Features

### Flexible Selection
All selection prompts support multiple input methods:

**Projects:**
- Number: `10` (from numbered list)
- Project key: `CAM` (exact match)
- Partial key: `CAM` (if unique)
- Project name: `CAMEL` (partial match)

**Issue Types:**
- Number: `0` (from numbered list)
- Type name: `Tarea` or `Historia`
- Partial name: `Hist` (matches Historia)

**Priorities:**
- Number: `2` (from numbered list)
- Priority name: `High` or `Medium`
- Skip: `skip` (use default)

**Assignees:**
- Number: `15` (from numbered list)
- Full name: `Miguel Lopez`
- Email: `miguel@company.com`
- Partial name: `Miguel`
- Unassigned: `unassigned`

### Smart Defaults
- **Active Sprint**: Automatically suggests current active sprint
- **Recent Epics**: Shows epics ordered by last updated
- **Team Members**: Lists assignable users for the project
- **Cross-Project**: Maintains settings when copying between projects

### Error Handling
- **Connection Testing**: Validates credentials before use
- **Graceful Fallbacks**: Continues even if optional features fail
- **Clear Messages**: Descriptive error messages with solutions
- **Multiple Matches**: Shows options when partial matches are ambiguous

## 🔧 Requirements

- **PHP**: ^8.1
- **Jira**: Cloud or Server with REST API access
- **Extensions**: `curl`, `json`

## 🎨 Advanced Usage

### Workflow Integration

Create tickets as part of your development workflow:

```bash
# Create a bug report for current issue
./vendor/bin/jira-wizard create-from PROJ-123

# Create feature ticket based on existing epic structure
./vendor/bin/jira-wizard create-from EPIC-456 --project NEWPROJ

# Quick task creation for sprint
./vendor/bin/jira-wizard create-from SPRINT-TEMPLATE
```

### Batch Operations

Use shell scripting for batch operations:

```bash
#!/bin/bash
# Create multiple related tickets
for feature in "login" "signup" "profile"; do
    echo "Creating ticket for $feature"
    ./vendor/bin/jira-wizard create-from TEMPLATE-123 \
        --project MYPROJ \
        --summary "Implement $feature feature"
done
```

### Team Productivity

**Project Templates:**
- Create a "template" ticket for each project type
- Use `create-from` to maintain consistency
- Share template ticket keys with team

**Sprint Planning:**
- Clone user stories with `create-from`
- Maintain epic linkage across related tickets
- Quickly create test tickets for each feature

### Custom Fields Support

The wizard automatically handles:
- **Standard Fields**: Summary, Description, Priority, Assignee
- **Project Fields**: Issue Types, Components, Versions
- **Agile Fields**: Sprint, Epic, Story Points
- **Custom Fields**: (via template copying)

## 🐛 Troubleshooting

### Common Issues

#### Connection Failed
```bash
❌ Connection failed: Unauthorized
```
**Solution**: Check your email and API token. Regenerate token if needed.

#### No Projects Found
```bash
No projects found or no access to projects.
```
**Solution**: Ensure your account has access to at least one Jira project.

#### Permission Denied
```bash
Failed to create issue: Forbidden
```
**Solution**: Verify you have permission to create issues in the selected project.

#### Template Ticket Not Found
```bash
❌ Ticket CAM-1106 not found or no access.
```
**Solution**: Check the ticket key and ensure you have access to view it.

#### Invalid Project Override
```bash
Project INVALID not found or no access.
```
**Solution**: Verify the project key exists and you have access to it.

### Debug Information

```bash
# Check current status and configuration
./vendor/bin/jira-wizard status

# View detailed configuration
cat ~/.jira-cli-config.json

# Test connection manually
curl -u "email@example.com:api-token" \
  "https://yourcompany.atlassian.net/rest/api/3/myself"

# Test specific ticket access
curl -u "email@example.com:api-token" \
  "https://yourcompany.atlassian.net/rest/api/3/issue/CAM-1106"
```

### Performance Tips

- **Connection Caching**: The CLI tests connection once per session
- **Project Caching**: Project lists are cached during wizard execution
- **API Optimization**: Minimal API calls for better performance
- **Batch Operations**: Use templates for creating multiple similar tickets

### Getting Help

- 📖 [Documentation](https://github.com/mi-lopez/jira-cli-wizard/wiki)
- 🐛 [Report Issues](https://github.com/mi-lopez/jira-cli-wizard/issues)
- 💬 [Discussions](https://github.com/mi-lopez/jira-cli-wizard/discussions)
- 📧 [Contact](mailto:your-email@example.com)

## 🧪 Development

### Setup Development Environment

```bash
# Clone the repository
git clone https://github.com/mi-lopez/jira-cli-wizard.git
cd jira-cli-wizard

# Install dependencies
composer install

# Run tests
composer test

# Check code style
composer cs-check

# Fix code style
composer cs-fix

# Run static analysis
composer phpstan
```

### Project Structure

```
├── bin/
│   └── jira-wizard                              # CLI entry point
├── src/
│   ├── Commands/
│   │   ├── CreateTicketCommand.php              # Interactive wizard + non-interactive mode
│   │   ├── CreateFromCommand.php                # Template creation command
│   │   ├── UpdateCommand.php                    # Field updates on existing tickets
│   │   ├── ViewCommand.php                      # Read a ticket in the terminal
│   │   ├── ListCommand.php                      # JSON resource listing
│   │   ├── ConfigureCommand.php                 # Configuration command
│   │   └── StatusCommand.php                    # Status command
│   ├── Helpers/
│   │   ├── AdfToText.php                        # Jira rich text back to Markdown
│   │   ├── AssigneeResolver.php                 # Name/email/account id/`me` resolution
│   │   ├── LabelParser.php                      # Label list parsing
│   │   ├── MarkdownToAdf.php                    # Markdown to Jira rich text
│   │   └── ConsoleHelper.php                    # Pretty console output
│   ├── JiraApiClient.php                        # Jira API integration
│   ├── ConfigManager.php                        # Configuration management
│   └── Installer.php                            # Post-install setup
├── tests/                                       # PHPUnit tests
├── .github/
│   └── workflows/                               # GitHub Actions CI
├── composer.json                                # Package configuration
└── README.md                                    # This file
```

### Running Tests

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run specific test
./vendor/bin/phpunit tests/Unit/ConfigManagerTest.php
```

## 🤝 Contributing

We welcome contributions! Please follow these steps:

1. **Fork** the repository
2. **Create** a feature branch (`git checkout -b feature/amazing-feature`)
3. **Make** your changes
4. **Add** tests for new functionality
5. **Ensure** all tests pass (`composer test`)
6. **Check** code style (`composer cs-check`)
7. **Commit** your changes (`git commit -m 'Add amazing feature'`)
8. **Push** to the branch (`git push origin feature/amazing-feature`)
9. **Open** a Pull Request

### Code Style

This project follows PSR-12 coding standards:

```bash
# Check code style
composer cs-check

# Auto-fix code style issues
composer cs-fix
```

## 🔒 Security

### API Token Security

- API tokens are stored locally in `~/.jira-cli-config.json`
- Tokens are never logged or transmitted except to Jira
- Use file permissions to protect your config: `chmod 600 ~/.jira-cli-config.json`

### Best Practices

- **Generate dedicated tokens**: Create a token specifically for CLI use
- **Regular rotation**: Rotate API tokens periodically
- **Minimal permissions**: Use accounts with minimal required permissions
- **Secure storage**: Keep your config file secure

## 📋 Roadmap

### Version 1.1.0
- [ ] **Bulk Operations**: Create multiple tickets at once
- [ ] **Custom Templates**: Save and reuse ticket templates locally
- [ ] **Custom Fields**: Enhanced support for custom Jira fields
- [ ] **Watchers**: Add watchers to tickets during creation
- [ ] **Project Shortcuts**: Quick project selection via aliases

### Version 1.2.0
- [ ] **Comments**: Add initial comments to tickets
- [ ] **Attachments**: Upload files to tickets
- [ ] **Sub-tasks**: Create sub-tasks automatically
- [ ] **Time Tracking**: Add time estimates and logging
- [ ] **Workflows**: Support for custom workflow transitions

### Version 2.0.0
- [ ] **Multiple Instances**: Support multiple Jira instances
- [ ] **Plugins**: Plugin system for extensions
- [ ] **GUI Mode**: Optional web interface
- [ ] **AI Integration**: AI-powered descriptions and summaries
- [ ] **Git Integration**: Create tickets from git commits/branches

## 📊 Performance

- **Cold start**: ~200ms (first run after configuration)
- **Warm start**: ~100ms (subsequent runs)
- **Template operations**: ~150ms (including API calls)
- **API calls**: Optimized to minimize requests
- **Memory usage**: ~12MB typical usage

## 🌟 Star History

If this tool saves you time, please consider giving it a star! ⭐

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- **Atlassian**: For providing the excellent Jira REST API
- **Symfony Console**: For the powerful CLI framework
- **Guzzle HTTP**: For reliable HTTP client functionality
- **Contributors**: All the amazing people who help improve this tool

## 📈 Changelog

### [1.5.0] - 2026-08-19

Added
- 🔀 `update --status` — move a ticket through its workflow by transition name, id or target status name, matched case-insensitively; unmatched values report what is legal from the current status, and asking for the status the ticket already has is a no-op
- 🔍 `list transitions --issue=KEY` — the transitions available from where the ticket stands, for scripts and AI agents

### [1.4.0] - 2026-08-19

Added
- 💬 `view --comments` (`-c`) — appends the comment thread, oldest first, with author, date and an `(edited …)` marker; paginated, so long threads come back whole

Fixed
- 🐛 **`--version` reported `1.0.0` on every release.** The string was hardcoded when the CLI was written and never bumped; it now comes from Composer's installed version

### [1.3.0] - 2026-08-19

Added
- 👀 `view` command — print a ticket's fields and description in the terminal, with `--json` for scripts and AI agents
- 🔤 `AdfToText` helper — converts Jira's ADF back to the Markdown flavour `create` and `update` accept, so a description survives a read/edit round trip

### [1.2.0] - 2026-08-05

**⚠️ Upgrade strongly recommended: `1.1.0` does not start on current Symfony.**

Added
- ✏️ `update` command — modify summary, description, type, epic/parent, priority, assignee, labels and sprint on an existing ticket, interactively or by flag, with `--dry-run`
- 📎 Attachment upload on `create`, `create-from` and `update` via the repeatable `--attachment` flag, plus a wizard prompt
- 👤 `--assignee` (`-a`) in non-interactive mode, resolving display name, email, account id or `me`
- 📝 Markdown descriptions rendered as real Jira ADF — headings, bullet and ordered lists, bold, italic, inline code, links and hard breaks
- 🏷️ Labels prompted in the `create` wizard, and prefilled from the source ticket in `create-from`

Fixed
- 🐛 **The CLI failed to start on symfony/console 6.0 and 7.4.** Every command class redeclared `$defaultName` with a type the parent declares untyped, which is a fatal error in PHP. Only 7.3.x happened to work, so `1.1.0` is broken on a fresh install. Commands now use `#[AsCommand]`
- 🐛 **Attachment uploads were never sent correctly.** The request cleared its `Content-Type` with a null value, which makes Guzzle skip `multipart/form-data`, so the body went out with no boundary for the server to parse
- 🐛 **`--dry-run` could create a real ticket.** Without the non-interactive flags it fell through to the wizard, which ends by creating the ticket. It now fails up front
- 🐛 An ambiguous `--assignee` no longer silently picks the first partial match; it lists the candidates and stops
- 🐛 Issue types without a `description` no longer emit a PHP warning
- 🐛 `getIssue()` now requests the `labels` field

Internal
- ✅ Test suite grew from 31 to 114 tests; line coverage from ~19% to ~44%
- 🔌 `JiraApiClient` is injectable into the commands, and accepts an HTTP client, so the suite runs fully offline
- 🔁 The two duplicated assignee resolvers were merged into one tested helper
- ⚙️ **CI now runs.** It had never executed once: it triggered on `main`/`develop` while the default branch is `master`. Test Analytics and Codecov PR reporting are wired up

### [1.1.0] - 2026-06-02
- 🤖 **NEW**: Non-interactive mode for `create` — pass all fields as flags, get issue key on stdout
- 🧪 **NEW**: `--dry-run` flag — preview the full JSON payload without creating any ticket
- 🔍 **NEW**: `list` command — outputs projects, issue-types, priorities, epics, and sprints as JSON
- 🏃 **NEW**: `--sprint=active` — auto-resolves the current active sprint at runtime
- 🐛 **FIX**: `getEpics()` migrated from deprecated `/rest/api/3/search` to `/rest/api/3/search/jql`

### [1.0.0] - 2025-07-03
- 🎉 Initial release
- ✨ Interactive ticket creation wizard
- 🔧 One-command configuration setup
- 🎯 Smart defaults for sprints and epics
- 🎨 Beautiful terminal interface
- 📊 Status and health checking
- 🔒 Secure API token authentication
- 🚀 **NEW**: Create from existing ticket templates
- 🔄 **NEW**: Cross-project ticket copying
- 🔍 **NEW**: Smart search and selection
- ⚡ **NEW**: Quick ticket creation workflows

---

<div align="center">

**Made with ❤️ and PHP by [mi-lopez](https://github.com/mi-lopez)**

[⬆ Back to top](#-jira-cli-wizard)

</div>