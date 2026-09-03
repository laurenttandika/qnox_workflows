# Qnox Workflows 2.x

An opinionated sequential approval engine for Laravel 10–13 and PHP 8.3+. It manages approval state and audit history; the consuming module owns business effects such as leave balances and payments.

## Install

```bash
composer require qnox/workflows:^2.0
php artisan vendor:publish --tag=qnox-workflows-config
php artisan migrate
```

The package views are loaded automatically under the `workflows::` namespace, so publishing them is optional. To customize the settings, inbox, or instance screens, publish them into the consuming application:

```bash
php artisan vendor:publish --tag=qnox-workflows-views
```

Laravel copies them to `resources/views/vendor/workflows`. Those published files override the package templates. After upgrading the package, compare customized copies with the current package views; use `--force` only when you intentionally want to overwrite local customizations.

The migration can also be published when the consuming application needs to own it:

```bash
php artisan vendor:publish --tag=qnox-workflows-migrations
```

Register integration namespaces in `config/workflows.php`:

```php
'modules' => [
    'hr.leave' => 'Leave',
    'hr.expenses' => 'Expenses',
    'procurement.requisitions' => 'Purchase Requisitions',
],
```

Modules are application-owned and read-only in the settings UI. The registry can also be extended during boot:

```php
app(\Qnox\Workflows\Contracts\ModuleRegistry::class)
    ->register('travel.requests', 'Travel Requests');
```

## Required integrations

Bind contracts in the consuming application's service provider. The package never assumes a supervisor column, user model, or permission library.

```php
use Qnox\Workflows\Contracts\{SupervisorResolver, RoleProvider, RoleAssigneeResolver, UserProvider};

$this->app->bind(SupervisorResolver::class, App\Workflows\SupervisorResolver::class);
$this->app->bind(RoleProvider::class, App\Workflows\SpatieRoleAdapter::class);
$this->app->bind(RoleAssigneeResolver::class, App\Workflows\SpatieRoleAdapter::class);
$this->app->bind(UserProvider::class, App\Workflows\UserProvider::class);
```

A supervisor resolver is deliberately application-specific:

```php
final class SupervisorResolver implements \Qnox\Workflows\Contracts\SupervisorResolver
{
    public function resolve(Authenticatable $initiator, array $context = []): ?Authenticatable
    {
        return $initiator->supervisor; // return null if absent or ineligible
    }
}
```

A Spatie adapter is optional, not an engine dependency. Its `options()` method returns arrays shaped as `['value' => $role->name, 'label' => $role->name]`; `resolve()` returns eligible `Authenticatable` users holding that role. A `UserProvider` supplies the same value/label shape, implements `findMany()`, and decides eligibility in `isEligible()`.

## Configure workflows

Open `/settings/workflows`, choose a registered module, and create a workflow. Each ordered level has a name, one approver source (`supervisor`, `role`, or `users`), and a rejection-comment option. The first level is always the start and the last is always final. The initiator is the applicant, never an implicit approval level.

For example, a three-level leave workflow may be configured as:

1. Line Manager Approval — supervisor
2. HR Review — role
3. Director Approval — selected users

Starting the workflow enters level 1 automatically. Approving a non-final level closes that level and enters the next ordered level. Approving level 3, the last level in this example, closes the workflow with the `approved` status and sets `approved_at`. Administrators do not configure separate start or final flags.

The application selects the exact definition:

```php
$workflow = Workflow::where('module_key', 'hr.leave')->where('slug', 'standard')->firstOrFail();
$instance = $engine->start(subject: $leave, workflow: $workflow, initiator: $user, context: []);
$instance = $engine->approve(instance: $instance, actor: $manager, comment: 'Approved');
$instance = $engine->reject(instance: $instance, actor: $hr, comment: 'Dates overlap');
$instance = $engine->cancel(instance: $instance, actor: $user, comment: 'Withdrawn');
```

Useful reads include `currentApprovalLevel()`, `resolvedApprovers()`, `approvalHistory()`, `canApprove()`, `canReject()`, `WorkflowInstance::finalOutcome()`, and `WorkflowInbox::pendingFor()`.

Listen to `WorkflowStarted`, `ApprovalLevelEntered`, `ApprovalRecorded`, `WorkflowApproved`, `WorkflowRejected`, and `WorkflowCancelled`. They are dispatched after commit. Apply module-specific consequences only from final events.

### Rejection and corrected submissions

Rejection is final for the current workflow instance. Rejecting at any level records the actor and comment, closes the active level and all its inbox items, sets the instance status to `rejected`, sets `rejected_at`, and does not create another level.

The package does not return a rejected instance to the initiator or resume it after modification. If corrections are allowed, the consuming module should let the initiator edit or recreate the underlying business request according to its own policy, then start a new workflow instance from level 1. The rejected instance remains unchanged as audit history.

```php
$previous = $request->workflowInstances()
    ->where('status', 'rejected')
    ->latest('id')
    ->first();

// Authorize and save the corrected request in the consuming module first.
$newInstance = $engine->start(
    subject: $request,
    workflow: $workflow,
    initiator: $user,
    context: ['resubmission_of' => $previous?->id],
);
```

The `resubmission_of` context value is an optional application convention; the engine preserves it but does not interpret it. Applications that require a completely new business request can create or clone that request before calling `start()`.

## Runtime guarantees

Starting and advancing resolve eligible recipients transactionally. Recipients and level fields are snapshotted, self-approval is denied, one member completes a role/user level, rejection is terminal, inbox items close together, and row locks prevent duplicate decisions. Missing supervisors or empty eligible recipient sets fail without partial history.

## Upgrading from 1.x

Version 2 is a clean major release. There is intentionally no in-place 1.x data migration because the package had no deployed consumers when v2 was created. Remove the old package migrations before installing the single v2 migration and rebuild any development-only workflow data.

Removed APIs and tables: groups, database-backed modules, assignments, participants, transitions/guards, claims, number sequences, hold/resume/return/recall, raw JSON configuration, and dynamic action keys. The old `act()` API and legacy routes are removed. Replace them with `approve()` or `reject()` and register modules in configuration.

## Qnox Core integration checklist

- Register each Qnox Core module key and label.
- Bind a tenant-aware `SupervisorResolver` without coupling this package to `TenantUser`.
- Bind tenant-aware role and user providers; exclude inactive users.
- Select a concrete `Workflow` definition in each module before calling `start()`.
- Listen for final events to commit/release leave balances, payments, or procurement state.
- Add tenant scoping to models/resolvers if Qnox Core requires database tenancy.
- Map package permissions into the host authorization system.
- Test UUID/morph-map identifiers, queues, notifications, and concurrent decisions in the host app.

## License

MIT
