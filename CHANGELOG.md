# Changelog

All notable changes to `qnox/workflows` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project follows [Semantic Versioning](https://semver.org/).

## [2.0.0] - 2026-09-03

Version 2.0 replaces the configurable transition engine with a smaller, opinionated sequential approval workflow package.

### Added

- Registered workflow modules supplied by the consuming application through configuration or the `ModuleRegistry` contract.
- Simple workflow definitions containing a module key, name, generated slug, active state, and ordered approval levels.
- Exactly three initial approver sources:
  - The workflow initiator's dynamically resolved supervisor.
  - Any one eligible member of a configured role.
  - Any one eligible user from a selected list.
- `SupervisorResolver`, `RoleProvider`, `RoleAssigneeResolver`, `UserProvider`, and `ModuleRegistry` extension contracts.
- Default configuration-backed module registry and Eloquent user provider.
- Explicit exceptions for invalid workflow operations and conflicting decisions.
- Runtime snapshots for approval-level names, sequence numbers, approver types, rejection requirements, and resolved recipients.
- Dedicated `workflow_instance_approvers` records for auditable resolved recipients.
- Explicit `approved_at`, `rejected_at`, and `cancelled_at` timestamps.
- Transactional `start()`, `approve()`, `reject()`, and initiator-only `cancel()` operations.
- Row locking and terminal-state checks to prevent concurrent or duplicate decisions.
- Backend methods for reading the current approval level, resolved approvers, approval history, actor eligibility, and final outcome.
- Pending-inbox lookup through `WorkflowInbox::pendingFor()`.
- After-commit events:
  - `WorkflowStarted`
  - `ApprovalLevelEntered`
  - `ApprovalRecorded`
  - `WorkflowApproved`
  - `WorkflowRejected`
  - `WorkflowCancelled`
- A simplified administration experience containing:
  - A read-only registered-module list.
  - A module-specific workflow list.
  - A complete workflow editor with ordered levels.
- Move-up, move-down, and removal controls for approval levels.
- Conditional role and selected-user fields.
- Search filtering for selected-user options.
- Confirmation and backend protection when reordering or removing levels from workflows with active instances.
- Nested workflow validation with field-specific validation messages and preserved form input.
- Automatic workflow slug generation when the form omits the nullable slug field.
- Module-scoped generated slug deduplication using suffixes such as `leave-approval-2`.
- Support for integer, string, and UUID-style user identifiers in definition and runtime tables.
- A Laravel Testbench and PHPUnit test suite covering schema creation, settings validation, runtime snapshots, sequential advancement, approval, rejection, cancellation, self-approval prevention, rollback behavior, inbox closure, and duplicate decisions.
- Installation, configuration, view publishing, migration publishing, integration, and upgrade documentation.
- A dedicated Qnox Core integration checklist.

### Changed

- Workflow modules are now integration namespaces instead of database-managed product records.
- The consuming application must select the exact workflow definition when starting an instance.
- The first ordered approval level is always the start level.
- The final ordered approval level is always the final level.
- One eligible role member or selected user completes the current approval level.
- Supervisor resolution happens when the workflow reaches the relevant level rather than when the definition is created.
- Resolved approvers are snapshotted so later supervisor, role-membership, or selected-user changes do not alter runtime history.
- Eligibility is checked during recipient resolution and again when an actor submits a decision.
- Rejection now has a distinct terminal `rejected` status and timestamp instead of being represented as successful completion.
- All inbox items for an approval level close after one eligible actor approves or rejects it.
- Package views continue to load under the `workflows::` namespace and can be published with the `qnox-workflows-views` tag.
- Package migrations can be published with the `qnox-workflows-migrations` tag.

### Removed

- Workflow groups.
- Database-managed workflow modules.
- Manual workflow assignments.
- Separate level participants and participant permission matrices.
- Manual transitions and dynamic action keys.
- Transition directions, guards, status strings, metadata, and form-schema JSON.
- Raw criteria and level-rules JSON.
- Configurable start and terminal flags.
- Optional levels and repeated participation.
- Pooled assignment, manual claiming, direct assignment, and round-robin behavior.
- Hold, resume, return, and recall actions.
- Number-sequence tables, services, settings pages, and model concerns.
- Legacy settings pages for groups, modules, participants, user permissions, transitions, and number formats.
- Legacy `act()` API and legacy workflow-instance action routes.
- Legacy events and next-approver notification implementation.

### Breaking changes

- The previous migrations have been removed and replaced by one clean 2.0 schema migration.
- There is no automatic 1.x data conversion. This decision was made because the previous schema had no public/deployed consumers.
- Development databases using the old schema must be rebuilt before installing 2.0.
- Consumers must register module keys in `config/workflows.php` or through `ModuleRegistry`.
- Consumers using supervisor or role approval levels must bind the corresponding resolver contracts.
- Applications must replace calls to `act()` with `approve()`, `reject()`, or `cancel()`.
- Applications must replace listeners for `WorkflowCompleted` with `WorkflowApproved` where successful final approval is intended.
- Business consequences such as leave-balance updates, payment processing, and procurement status changes must be handled by consuming applications through final workflow events.

### Fixed

- Prevented an undefined-array-key error when the exposed workflow form submits no slug.
- Prevented an initiator from approving their own workflow, including when returned by a supervisor, role, or selected-user resolver.
- Prevented inactive or otherwise ineligible users from being resolved or acting on an approval level.
- Prevented partial workflow records when recipient resolution fails during start or advancement.
- Prevented rejection from creating the next approval level.
- Prevented definition edits from changing existing runtime level history or resolved recipients.
