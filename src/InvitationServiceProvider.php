<?php

declare(strict_types=1);

namespace Vimatech\Invitation;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class InvitationServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('invitation')
            ->hasConfigFile()
            ->hasMigration('create_invitations_table')
            ->hasViews();
    }

    public function packageBooted(): void
    {
        if (config('invitation.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }
    }

    public function packageRegistered(): void
    {
        $this->app->bind(InvitationManager::class, function () {
            return new InvitationManager;
        });
    }
}
