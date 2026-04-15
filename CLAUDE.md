# Claude Code — Laravel Project

## Project

**sale-pro** — a multi-role Laravel SaaS for managing an e-commerce business.

| Who | What they can do |
|-----|-----------------|
| Super Admin | Full access, manage roles and permissions |
| Admin | Manage customers, users, departments |
| Staff | View and work with customers (scoped by permission) |
| Customer | Log into the customer portal, view their own profile |

**Modules built so far:**
- Auth — Breeze login/register, email verification
- Users — admin user management with role assignment
- Permissions — Spatie roles/permissions, DB-driven
- Departments — organisational structure
- Customers — e-commerce customer records, status pipeline, source tracking
- Customer Portal — customers log in via their own portal layout

**Plans for each module:** `.claude/plans/<module>/` — always check here before implementing anything.

---

## Routing Architecture

Two distinct sides — admin and customer (portal). Clean URL separation.

### Admin side — `/admin/*`
All staff/admin routes live under the `/admin/` prefix.

| URL | Purpose |
|-----|---------|
| `/` | Redirects to `/admin/login` |
| `/admin/login` | Admin login (named: `login`) |
| `/admin/register` | Admin register (named: `register`) |
| `/admin/dashboard` | Admin dashboard (named: `dashboard`) |
| `/admin/profile` | Admin profile (named: `profile.edit`) |
| `/admin/users` | User management |
| `/admin/departments` | Department management |
| `/admin/customers` | Customer management |
| `/admin/roles` | Role management |

Middleware stack: `auth`, `load_perms`, `verified`, `active`

### Customer (Portal) side — root `/`
Customer-facing routes live at the root. This is the primary ecommerce side.

| URL | Purpose |
|-----|---------|
| `/` | Redirects to `/login` (customer login) |
| `/login` | Customer login (named: `portal.login`) |
| `/register` | Customer register (named: `portal.register`) |
| `/dashboard` | Customer dashboard (named: `portal.dashboard`) |
| `/profile` | Customer profile (named: `portal.profile.show`) |
| `/forgot-password` | Customer password reset (named: `portal.password.request`) |

Middleware stack: `auth`, `verified:portal.verification.notice`, `role:customer`, `active`

> Named routes use `portal.` prefix — `route('portal.login')`, `route('portal.dashboard')`, etc.
> Admin named routes have no prefix — `route('login')` = `/admin/login`, `route('dashboard')` = `/admin/dashboard`.

---

## Behavior Protocol — ACT vs ASK

### ACT immediately (no confirmation needed)
- Task matches a plan file in `.claude/plans/` — follow the plan exactly
- Change clearly follows an established pattern in `skills/references/`
- Fixing a test, bug, or type error with a clear root cause
- Adding a file that mirrors an existing file's pattern

### STOP and ask ONE question when
- Requirements conflict with an existing pattern
- Scope is ambiguous — unclear which files or which approach
- A destructive change is required (delete, drop table, major refactor)
- The plan says X but the existing code does Y — which is correct?

### Rules for asking
- ONE question only — never a list of questions
- Ask the most important blocking question
- Never ask if the answer is readable from a plan file or existing code
- Never ask "are you sure?" — if asked to do something, do it

## Stack
- PHP 8.2+, Laravel 12, MySQL/MariaDB
- Auth: Laravel Breeze (Blade)
- Permissions: Spatie Laravel Permission
- Frontend: Blade + Tailwind CSS v3
- Queue: database driver
- Testing: Pest
- Code style: Pint (PSR-12) + PHPStan level 8

## Skills
Before any Laravel design, architecture, or implementation task, read and follow:
/Users/npc/sale-pro/.claude/skills/laravel-system-design.md

Reference files are at:
/Users/npc/sale-pro/.claude/skills/references

## Rules

### Review Mode (CRITICAL)
When the user asks you to **check**, **review**, **audit**, **inspect**, or **report** on code,
tests, or any files — you MUST:
1. Read and analyse the relevant files
2. Produce a detailed written report (findings, issues, risks, recommendations)
3. **STOP — do NOT edit, write, or create any file**

Only proceed to make changes if the user explicitly says so AFTER reading the report
(e.g. "yes fix it", "go ahead", "update it"). A review request is never implicit permission to edit.

### File Edit Approval (CRITICAL)
Before editing or creating any file, you MUST:
1. Show the user exactly what you plan to change (file path + a brief description of the change)
2. Wait for explicit approval ("yes", "go ahead", "do it", etc.)
3. Only then proceed with the Edit or Write tool

Do NOT batch multiple file changes into a single approval request — ask per file (or per logical group if they are tightly coupled and the user has already approved the overall task).

### Laravel Rules
- No Repository pattern. No DTOs. No Docker initially.
- Flow is always: Request → FormRequest → Controller → Service → Model → Response
- strict_types=1 on every file
- $request->validated() always, never $request->all()
- with() always, never lazy load
- DB::transaction for every multi-table write
- Every controller action has a Pest feature test
- Every service method has a Pest unit test

## Project location
/Users/npc/sale-pro

## Project Plans 
Feature designs and architecture decisions are in `.claude/plans/`.
Before implementing any feature, check if a plan exists there first.

<!-- code-review-graph MCP tools -->
## MCP Tools: code-review-graph

**IMPORTANT: This project has a knowledge graph. ALWAYS use the
code-review-graph MCP tools BEFORE using Grep/Glob/Read to explore
the codebase.** The graph is faster, cheaper (fewer tokens), and gives
you structural context (callers, dependents, test coverage) that file
scanning cannot.

### Always use graph tools — no exceptions

- **Exploring code**: `semantic_search_nodes` or `query_graph`
- **Understanding impact**: `get_impact_radius`
- **Code review**: `detect_changes` + `get_review_context`
- **Finding relationships**: `query_graph` with callers_of/callees_of/imports_of/tests_for
- **Architecture questions**: `get_architecture_overview` + `list_communities`

### Key Tools

| Tool | Use when |
|------|----------|
| `detect_changes` | Reviewing code changes — gives risk-scored analysis |
| `get_review_context` | Need source snippets for review — token-efficient |
| `get_impact_radius` | Understanding blast radius of a change |
| `get_affected_flows` | Finding which execution paths are impacted |
| `query_graph` | Tracing callers, callees, imports, tests, dependencies |
| `semantic_search_nodes` | Finding functions/classes by name or keyword |
| `get_architecture_overview` | Understanding high-level codebase structure |
| `refactor_tool` | Planning renames, finding dead code |

### Workflow

1. The graph auto-updates on file changes (via hooks).
2. Use `detect_changes` for code review.
3. Use `get_affected_flows` to understand impact.
4. Use `query_graph` pattern="tests_for" to check coverage.

## Hooks (Auto-Enforced — No Manual Action Needed)

| Event | Trigger | What it does |
|-------|---------|--------------|
| PreToolUse | Edit or Write any `.php` file | Outputs targeted pattern reminder for that file type (Controller/Service/Model/etc.) |
| PostToolUse | Edit or Write any `.php` file | Auto-runs Pint to format the file |
| PostToolUse | Edit, Write, or Bash | Updates the code-review knowledge graph |
| Stop | End of session | Runs `detect-changes --brief` summary |

Pint runs automatically — never run it manually after edits.
