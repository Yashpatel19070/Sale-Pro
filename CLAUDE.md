# Claude Code — Laravel Project

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

### When to use graph tools FIRST

- **Exploring code**: `semantic_search_nodes` or `query_graph` instead of Grep
- **Understanding impact**: `get_impact_radius` instead of manually tracing imports
- **Code review**: `detect_changes` + `get_review_context` instead of reading entire files
- **Finding relationships**: `query_graph` with callers_of/callees_of/imports_of/tests_for
- **Architecture questions**: `get_architecture_overview` + `list_communities`

Fall back to Grep/Glob/Read **only** when the graph doesn't cover what you need.

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
