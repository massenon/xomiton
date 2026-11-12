---
type: moc
phase: 05
title: Development
updated: 2026-11-12
---

# Development

> Worktree tracking, branches and correlation with tasks and PRs — the live state of execution.

## Purpose

This folder records the work in progress: active worktrees, branches linked to tasks and PRs, assigned agents. NOT here: source code (lives in the repository), nor deploy history (lives in CI/CD).

## Subfolders

- `worktrees/` — one file per worktree with branch, path, linked task and status.
- `branches/` — correlation between branch, task and PR with CI status.

## Artifacts

_No artifacts yet._

## Available templates

- `_templates/worktree.md.tpl` — log of active/merged/abandoned worktree.
- `_templates/branch.md.tpl` — branch ↔ task ↔ PR correlation.

## Useful wikilinks

- [[CLAUDE]] — vault doctrine
- [[_INDEX]] — global index
