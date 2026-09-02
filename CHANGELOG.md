# Changelog

## 2.0.0 - 2026-09-02

- Rebuilt the package as a sequential approval engine with exactly supervisor, role, and selected-user approvers.
- Added configuration/programmatic module registration and a three-screen settings experience.
- Added typed definitions, selected-user relations, runtime level/approver snapshots, explicit final statuses, and terminal timestamps.
- Added transactional start/approve/reject APIs, backend eligibility checks, row locks, inbox closure, and after-commit events.
- Removed the unreleased 1.x schema and legacy groups, database modules, transitions, assignments, participants, claims, number sequences, advanced actions, and JSON editors.
- Added migration and Qnox Core integration guidance.
