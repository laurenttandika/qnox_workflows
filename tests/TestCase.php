<?php
namespace Qnox\Workflows\Tests;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Qnox\Workflows\Contracts\{RoleAssigneeResolver, RoleProvider, SupervisorResolver, UserProvider};
use Qnox\Workflows\Tests\Fixtures\{ApproverProvider, RoleResolver, SupervisorResolver as TestSupervisorResolver, User};
use Qnox\Workflows\WorkflowsServiceProvider;
abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array { return [WorkflowsServiceProvider::class]; }
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]);
        $app['config']->set('workflows.modules', ['hr.leave' => 'Leave']);
        $app['config']->set('workflows.user_model', User::class);
        $app['config']->set('workflows.routes.web.enabled', true);
        $app['config']->set('workflows.routes.api.enabled', false);
    }
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database'=>'testing'])->run();
        Schema::create('test_users', function(Blueprint $t){$t->id();$t->string('name');$t->boolean('is_active')->default(true);$t->timestamps();});
        Schema::create('test_subjects', function(Blueprint $t){$t->id();$t->string('name');$t->timestamps();});
        $provider = new ApproverProvider();
        $this->app->instance(ApproverProvider::class, $provider);
        $this->app->instance(RoleProvider::class, $provider);
        $this->app->instance(UserProvider::class, $provider);
        $this->app->instance(SupervisorResolver::class, new TestSupervisorResolver());
        $this->app->instance(RoleAssigneeResolver::class, new RoleResolver());
    }
    protected function provider(): ApproverProvider { return $this->app->make(ApproverProvider::class); }
    protected function supervisors(): TestSupervisorResolver { return $this->app->make(SupervisorResolver::class); }
    protected function roles(): RoleResolver { return $this->app->make(RoleAssigneeResolver::class); }
}
