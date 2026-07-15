# Multi-agent workflow

For heavy tasks or changes touching many files/areas in this repo, delegate to subagents via the Agent tool instead of doing everything inline:

- Split the work into independent subtasks and dispatch them as parallel Agent calls (see `superpowers:dispatching-parallel-agents`).
- For multi-step implementation plans, use `superpowers:subagent-driven-development` to run independent tasks as subagents in-session.
- Use `Explore` for research/investigation subtasks, `general-purpose` for implementation subtasks.
- Act as the orchestrator: brief each agent with full context (it starts cold), collect results, and synthesize.

Skip this for small, single-file, or straightforward changes — no need to spin up agents for a one-line fix.
