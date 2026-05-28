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
- PHP 8.5, Laravel 13.4 , MySQL/MariaDB
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
Before editing any file, read it first. Before modifying a function, grep for all callers. Research before you edit.
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

## Status Tracking (CRITICAL)

After completing **any task** — feature, bug fix, plan update, or test — you MUST:
1. Open `.claude/plans/STATUS.md`
2. Find the affected module row(s)
3. Update `%`, `Status`, and `Last Touched`
4. Save the file

**No exceptions.** A task is not complete until STATUS.md is updated.

Status values: `not started` / `in progress` / `done` / `deferred`

% guide: 20% schema/migration · 40% model+service · 60% controller+routes · 80% views · 90% tests written · 100% all passing

<!-- codegraph MCP tools -->
## MCP Tools: codegraph

**IMPORTANT: This project has a knowledge graph (`codegraph`). ALWAYS use the
codegraph MCP tools BEFORE using Grep/Glob/Read to explore the codebase.**
The graph is faster, cheaper (fewer tokens), and gives structural context
(callers, callees, impact radius) that file scanning cannot.

### Always use graph tools — no exceptions

- **"What's the deal with feature/area X?"** → `codegraph_context` (PRIMARY — composes search + node + callers + callees in one call)
- **Find a symbol by name** → `codegraph_search`
- **Trace a flow X→Y** → `codegraph_trace`
- **What calls this / what does this call** → `codegraph_callers` / `codegraph_callees`
- **Blast radius of a change** → `codegraph_impact`
- **Show one symbol's source** → `codegraph_node`; **survey several** → `codegraph_explore`
- **What's in a directory** → `codegraph_files`
- **Is the index ready** → `codegraph_status`

### Key Tools

| Tool | Use when |
|------|----------|
| `codegraph_context` | Understanding a task/feature/area — primary composite call |
| `codegraph_trace` | Tracing the path from X to Y |
| `codegraph_callers` / `codegraph_callees` | Finding what calls / is called |
| `codegraph_impact` | Understanding blast radius of a change |
| `codegraph_search` | Finding a function/class by name |
| `codegraph_node` / `codegraph_explore` | Reading symbol source (one / many) |
| `codegraph_files` | Listing a directory's contents |
| `codegraph_status` | Checking index readiness/size |

### Workflow

1. The graph auto-updates on file changes (via the PostToolUse hook).
2. Start with `codegraph_context` for any area question.
3. Use `codegraph_trace` to follow execution paths.
4. Use `codegraph_impact` before refactoring.

## Hooks (Auto-Enforced — No Manual Action Needed)

| Event | Trigger | What it does |
|-------|---------|--------------|
| PreToolUse | Edit or Write any `.php` file | Outputs targeted pattern reminder for that file type (Controller/Service/Model/etc.) |
| PostToolUse | Edit or Write any `.php` file | Auto-runs Pint to format the file |
| PostToolUse | Edit, Write, or Bash | Updates the codegraph knowledge graph |
| Stop | End of session | Runs an end-of-session change summary |

Pint runs automatically — never run it manually after edits.

## Coding Behavior — Karpathy Guidelines (ALWAYS FOLLOW — CRITICAL)

These behavioral guidelines apply **by default to every coding task** in this
project — no need to invoke the `andrej-karpathy-skills:karpathy-guidelines`
skill, treat the rules below as mandatory. (Bias toward caution over speed;
for trivial tasks, use judgment.)

### 1. Think Before Coding
Don't assume. Don't hide confusion. Surface tradeoffs.
- State assumptions explicitly; if uncertain, ask.
- If multiple interpretations exist, present them — don't pick silently.
- If a simpler approach exists, say so. Push back when warranted.
- If something is unclear, stop, name what's confusing, and ask.

### 2. Simplicity First
Minimum code that solves the problem. Nothing speculative.
- No features beyond what was asked.
- No abstractions for single-use code.
- No "flexibility"/"configurability" that wasn't requested.
- No error handling for impossible scenarios.
- If 200 lines could be 50, rewrite it. Ask: "Would a senior engineer call this overcomplicated?"

### 3. Surgical Changes
Touch only what you must. Clean up only your own mess.
- Don't "improve" adjacent code, comments, or formatting.
- Don't refactor things that aren't broken; match existing style.
- Note unrelated dead code — don't delete it unless asked.
- Remove only the imports/vars/functions YOUR changes orphaned.
- Test: every changed line traces directly to the user's request.

### 4. Goal-Driven Execution
Define success criteria. Loop until verified.
- "Add validation" → write tests for invalid inputs, then make them pass.
- "Fix the bug" → write a test that reproduces it, then make it pass.
- "Refactor X" → ensure tests pass before and after.
- For multi-step tasks, state a brief plan with a verify check per step.
