<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Notifications\Contracts\FcmSender;
use App\Domain\Notifications\Contracts\PushSender;
use App\Domain\Notifications\Contracts\SmsSender;
use App\Enums\DirectoryRole;
use App\Models\Alert;
use App\Models\Commune;
use App\Models\DirectoryContact;
use App\Models\OnCallShift;
use App\Models\PushSubscription;
use App\Models\User;
use App\Models\Zone;
use Tests\Support\Notifications\FakePushSender;
use Tests\Support\Notifications\FakeSmsSender;

/**
 * Scène du SOS de référence (P5-03, maquette mobile simulateur.html l.910) : zone Littoral / Cotonou, point focal de
 * zone (fiche publique, avec ou sans compte), point focal national, un admin de garde, une membre avec profil (contact
 * de confiance « Chantal ») et deux abonnements push (Web Push, FCM), puis le SOS routé vers le point focal.
 */
final class SosScenario
{
    public const TRUSTED_PHONE = '+229 01 97 38 88 47';

    public const FOCAL_PHONE = '+229 01 64 40 40 40';

    public Zone $zone;

    public Commune $commune;

    public DirectoryContact $focal;

    public DirectoryContact $national;

    public User $admin;

    public User $member;

    public ?User $focalUser = null;

    public Alert $alert;

    public FakePushSender $push;

    public FakeSmsSender $sms;

    /**
     * @param  bool  $focalWithAccount  la fiche du point focal est liée à un compte actif (cloche + push)
     * @param  bool  $memberPush  la membre a des abonnements push (sans effet sur ses propres notifications)
     */
    public static function make(bool $focalWithAccount = false, bool $anonymous = false, ?FakePushSender $push = null, ?FakeSmsSender $sms = null): self
    {
        $scene = new self;
        $scene->push = $push ?? new FakePushSender;
        $scene->sms = $sms ?? new FakeSmsSender;
        app()->instance(PushSender::class, $scene->push);
        app()->instance(FcmSender::class, $scene->push);
        app()->instance(SmsSender::class, $scene->sms);

        $scene->zone = Zone::query()->firstOrCreate(['slug' => 'littoral'], ['name' => 'Littoral']);
        $scene->commune = Commune::factory()->create(['zone_id' => $scene->zone->id, 'name' => 'Cotonou']);

        if ($focalWithAccount) {
            $scene->focalUser = User::factory()->withProfile()->create();
            PushSubscription::factory()->forUser($scene->focalUser)->create();
        }

        $scene->focal = DirectoryContact::factory()->focalZone($scene->zone)->create([
            'name' => 'Mireille TOGNIFODE',
            'phone' => self::FOCAL_PHONE,
            'email' => 'mireille@exemple.bj',
            'is_public' => true,
            'user_id' => $scene->focalUser?->id,
        ]);
        $scene->national = DirectoryContact::factory()->focalNational()->create(['name' => 'Point focal national', 'role' => DirectoryRole::PointFocal]);

        $scene->admin = User::factory()->withProfile()->create(['phone' => '+229 01 90 00 00 01']);
        $scene->admin->assignRole('admin');
        $scene->admin->givePermissionTo('alerts.take');
        OnCallShift::factory()->admin()->forUser($scene->admin)->create();

        $scene->member = User::factory()->withProfile(['trusted_contact_phone' => self::TRUSTED_PHONE, 'trusted_contact_name' => 'Chantal'])->create();
        PushSubscription::factory()->forUser($scene->member)->create();
        PushSubscription::factory()->fcm()->forUser($scene->member)->create();

        $factory = Alert::factory()->sos()->routedTo($scene->focal, 'focal_zone');
        $factory = $anonymous ? $factory : $factory->fromMember($scene->member);
        $scene->alert = $factory->create(['zone_id' => $scene->zone->id, 'commune_id' => $scene->commune->id]);

        return $scene;
    }
}
