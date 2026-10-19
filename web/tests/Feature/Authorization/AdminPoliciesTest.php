<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\EmailTemplate;
use App\Models\Faq;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\OutboundPost;
use App\Models\Resource;
use App\Models\Setting;
use App\Models\SocialChannel;
use App\Models\Thematique;
use Illuminate\Support\Facades\Gate;
use Tests\Feature\Authorization\Support\RoleUsers;

/*
 * Policies des modules d'administration (ROLE_ET_PERMISSIONS.md §3) : admin ✅ sauf les actions réservées
 * au superadmin (suppression système, restauration, SMTP, purge du journal).
 */

beforeEach(function (): void {
    RoleUsers::seedRoles();
    $this->users = RoleUsers::all();
    $this->admins = ['admin', 'superadmin'];
});

describe('contenu éditorial', function (): void {
    it('publie les contenus pour tout le monde et réserve l’édition à l’administration', function (string $model, string $draftState): void {
        $published = $model::factory()->create();
        $draft = $model::factory()->{$draftState}()->create();

        RoleUsers::assertAbility($this->users, 'viewAny', $model, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'view', $published, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'view', $draft, $this->admins);
        RoleUsers::assertAbility($this->users, 'create', $model, $this->admins);
        RoleUsers::assertAbility($this->users, 'update', $published, $this->admins);
        RoleUsers::assertAbility($this->users, 'publish', $draft, $this->admins);
        RoleUsers::assertAbility($this->users, 'delete', $published, $this->admins);

        expect(Gate::forUser(null)->allows('view', $published))->toBeTrue()
            ->and(Gate::forUser(null)->allows('view', $draft))->toBeFalse()
            ->and(Gate::forUser(null)->allows('viewAny', $model))->toBeTrue();
    })->with([
        'thématiques' => [Thematique::class, 'draft'],
        'ressources' => [Resource::class, 'draft'],
        'FAQ' => [Faq::class, 'unpublished'],
    ]);
});

describe('EmailTemplatePolicy', function (): void {
    it('réserve la suppression des templates système au superadmin', function (): void {
        $custom = EmailTemplate::factory()->create();
        $system = EmailTemplate::factory()->system()->create();

        RoleUsers::assertAbility($this->users, 'viewAny', EmailTemplate::class, $this->admins);
        RoleUsers::assertAbility($this->users, 'create', EmailTemplate::class, $this->admins);
        RoleUsers::assertAbility($this->users, 'view', $system, $this->admins);
        RoleUsers::assertAbility($this->users, 'update', $system, $this->admins);
        RoleUsers::assertAbility($this->users, 'sendTest', $system, $this->admins);
        RoleUsers::assertAbility($this->users, 'delete', $custom, $this->admins);
        RoleUsers::assertAbility($this->users, 'delete', $system, ['superadmin']);
        RoleUsers::assertAbility($this->users, 'deleteSystem', $system, ['superadmin']);
    });
});

describe('BackupPolicy', function (): void {
    it('laisse l’admin créer et télécharger, le superadmin restaurer et supprimer', function (): void {
        $backup = Backup::factory()->create();

        RoleUsers::assertAbility($this->users, 'viewAny', Backup::class, $this->admins);
        RoleUsers::assertAbility($this->users, 'view', $backup, $this->admins);
        RoleUsers::assertAbility($this->users, 'create', Backup::class, $this->admins);
        RoleUsers::assertAbility($this->users, 'download', $backup, $this->admins);
        RoleUsers::assertAbility($this->users, 'restore', $backup, ['superadmin']);
        RoleUsers::assertAbility($this->users, 'delete', $backup, ['superadmin']);
    });
});

describe('newsletter', function (): void {
    it('réserve campagnes et abonné·es à l’administration', function (): void {
        $campaign = NewsletterCampaign::factory()->create();
        $subscriber = NewsletterSubscriber::factory()->create();

        foreach (['viewAny', 'create'] as $ability) {
            RoleUsers::assertAbility($this->users, $ability, NewsletterCampaign::class, $this->admins);
            RoleUsers::assertAbility($this->users, $ability, NewsletterSubscriber::class, $this->admins);
        }
        foreach (['view', 'update', 'delete', 'send', 'sendTest', 'cancel'] as $ability) {
            RoleUsers::assertAbility($this->users, $ability, $campaign, $this->admins);
        }
        foreach (['view', 'update', 'delete'] as $ability) {
            RoleUsers::assertAbility($this->users, $ability, $subscriber, $this->admins);
        }
        RoleUsers::assertAbility($this->users, 'import', NewsletterSubscriber::class, $this->admins);
        RoleUsers::assertAbility($this->users, 'export', NewsletterSubscriber::class, $this->admins);
    });
});

describe('passerelles réseaux sociaux', function (): void {
    it('réserve la connexion des comptes et la file de publication à l’administration', function (): void {
        $channel = SocialChannel::factory()->create();
        $outbound = OutboundPost::factory()->create();

        RoleUsers::assertAbility($this->users, 'viewAny', SocialChannel::class, $this->admins);
        foreach (['view', 'connect', 'update', 'disconnect'] as $ability) {
            RoleUsers::assertAbility($this->users, $ability, $channel, $this->admins);
        }
        RoleUsers::assertAbility($this->users, 'viewAny', OutboundPost::class, $this->admins);
        RoleUsers::assertAbility($this->users, 'create', OutboundPost::class, $this->admins);
        foreach (['view', 'update', 'delete', 'publish', 'markPublished'] as $ability) {
            RoleUsers::assertAbility($this->users, $ability, $outbound, $this->admins);
        }
    });
});

describe('SettingPolicy', function (): void {
    it('laisse l’admin lire et réserve SMTP et modifications au superadmin', function (): void {
        $setting = Setting::factory()->create();
        $secret = Setting::factory()->secret()->create();

        RoleUsers::assertAbility($this->users, 'viewAny', Setting::class, $this->admins);
        RoleUsers::assertAbility($this->users, 'view', $setting, $this->admins);
        RoleUsers::assertAbility($this->users, 'view', $secret, ['superadmin']);
        RoleUsers::assertAbility($this->users, 'update', $setting, ['superadmin']);
        RoleUsers::assertAbility($this->users, 'update', $secret, ['superadmin']);
        RoleUsers::assertAbility($this->users, 'updateSmtp', Setting::class, ['superadmin']);
        RoleUsers::assertAbility($this->users, 'testSmtp', Setting::class, ['superadmin']);
        RoleUsers::assertAbility($this->users, 'toggleMaintenance', Setting::class, ['admin', 'superadmin']);
    });
});

describe('AuditLogPolicy', function (): void {
    it('ouvre la lecture à l’administration, la purge au superadmin et la modification à personne', function (): void {
        $log = AuditLog::factory()->create();

        RoleUsers::assertAbility($this->users, 'viewAny', AuditLog::class, $this->admins);
        RoleUsers::assertAbility($this->users, 'view', $log, $this->admins);
        RoleUsers::assertAbility($this->users, 'export', AuditLog::class, $this->admins);
        RoleUsers::assertAbility($this->users, 'purge', AuditLog::class, ['superadmin']);
        RoleUsers::assertAbility($this->users, 'create', AuditLog::class, []);
        RoleUsers::assertAbility($this->users, 'update', $log, []);
        RoleUsers::assertAbility($this->users, 'delete', $log, []);
    });
});
