# AI agents

The package ships an [agent skill](https://agentskills.io) (`inertia-static-development`) and short guidelines, so
coding agents know how to declare static routes, add islands, purge and deploy.

## Laravel Boost

With [Laravel Boost](https://laravel.com/docs/boost), they are installed with the others:

```bash
php artisan boost:install   # or boost:update when Boost is already installed
```

The guidelines go into your agent's guideline file (`CLAUDE.md`, `AGENTS.md`, …) and the skill into its skills
folder (`.claude/skills/`, …).

## Claude Code

Without Boost, install the plugin:

```text
/plugin marketplace add fydellys/inertia-static
/plugin install inertia-static@inertia-static
```

The skill loads when a task needs it, or run it with `/inertia-static:inertia-static-development`.

Only the skill, without the guidelines:

```bash
php artisan boost:add-skill fydellys/inertia-static
```

## Other agents

With the [skills](https://skills.sh) CLI (Cursor, Codex, Copilot, Gemini CLI and others):

```bash
npx skills add fydellys/inertia-static
```

Or copy `vendor/fydellys/inertia-static/resources/boost/skills/inertia-static-development` (the folder, with
`references/`) into your agent's skills folder, e.g. `.claude/skills/` or `.agents/skills/`.
