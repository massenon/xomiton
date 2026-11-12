<?php

declare(strict_types=1);

use App\Application\Content\ConversationsBd\ImportConversationsBd;
use App\Application\Seo\SitemapBuilder;
use App\Enums\BdConversationStatus;
use App\Models\BdConversation;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserSetting;

if (! function_exists('bdcNb')) {
    /** Typographie des fichiers de lang et des données : espace insécable avant « : ! ? ». */
    function bdcNb(string $text): string
    {
        return str_replace([' :', ' !', ' ?'], ["\u{00A0}:", "\u{00A0}!", "\u{00A0}?"], $text);
    }
}

/*
 * P20-06 / P20-10 : pages publiques des conversations en BD — liste /conversations-bd (publiées seulement), page
 * /conversations-bd/{slug} (cases en figure, texte des bulles dans le HTML, transcription lue dans l'ordre, repli sans
 * image, avertissement, fin de lecture avec liens d'aide, 404 neutre si non publiée), mode séance (noindex, pensées
 * repérables, questions), mode discret, plan du site, pied de page.
 */

beforeEach(function (): void {
    app(ImportConversationsBd::class)();
    // Les illustrations n'existent pas encore : l'import crée des brouillons ; on publie ici m01 à m11.
    BdConversation::query()->where('model_key', '!=', 'm12')->update(['status' => BdConversationStatus::Published->value, 'published_at' => now()]);
});

it('liste les conversations publiées seulement, avec leur nombre de cases', function (): void {
    $html = (string) $this->get('/conversations-bd')->assertOk()->getContent();

    expect($html)->toContain(route('conversations-bd.show', ['slug' => 'non-cest-non']))
        ->toContain('Non, c’est non')
        ->toContain('5 cases')
        ->not->toContain(route('conversations-bd.show', ['slug' => 'les-regles-du-groupe']))
        ->toContain(route('signaler'))->toContain(route('sos'))->toContain(route('urgences'))->toContain(route('aide.index'))
        ->toContain('index, follow');
});

it('sert une conversation : cases en figure, bulles au texte réel, transcription dans l’ordre, repli sans image', function (): void {
    $html = (string) $this->get('/conversations-bd/non-cest-non')->assertOk()->getContent();

    expect(substr_count($html, 'data-xm-comic-page'))->toBe(5)
        ->and(substr_count($html, '<figure class="xm-bdc-panel"'))->toBe(5)
        ->and(substr_count($html, 'class="xm-bdc-svg"'))->toBe(5)
        ->and(substr_count($html, 'class="xm-bdc-fallback"'))->toBe(5);
    expect($html)->toContain('id="case-1"')
        ->toContain(e(__('conversations_bd.panel.image_pending')))
        ->toContain('Rodrigue (à droite) se penche vers Fifamè')
        // Texte des bulles dans le SVG (indexable) et transcription (lecteurs d'écran), dans l'ordre de lecture.
        ->toContain('Il n’y aura personne.')
        ->toContain(bdcNb(e('Narration : À la sortie du lycée, à Porto-Novo.')))
        ->toContain(bdcNb(e('Rodrigue dit : Viens chez moi ce soir. Il n’y aura personne.')))
        ->toContain(bdcNb(e('Fifamè pense : Je n’en ai pas envie… mais je ne veux pas le vexer.')))
        ->toContain(bdcNb(e('Fifamè dit, mal à l’aise : Euh… je ne sais pas… peut-être.')))
        ->toContain(bdcNb('Rodrigue dit avec joie : Super !'))
        ->toContain('aria-hidden="true" focusable="false"')
        ->toContain("x-data='xmComic(")
        ->toContain('"anchor":"case"')
        ->toContain(e(__('pages/conversations_bd.viewer.next')))
        ->toContain('aria-live="polite"')
        // Fin de lecture : message clé, aide, mode séance et questions.
        ->toContain('Un non n’a pas besoin de justification')
        ->toContain(route('signaler'))->toContain(route('sos'))->toContain(route('urgences'))->toContain(route('aide.index'))
        ->toContain(route('conversations-bd.seance', ['slug' => 'non-cest-non']))
        ->toContain('Tu me dois bien ça')
        ->toContain('index, follow');

    // Ordre de lecture : la narration avant la parole de Rodrigue, puis la pensée de Fifamè.
    expect(strpos($html, bdcNb('Narration : À la sortie')))->toBeLessThan(strpos($html, bdcNb('Rodrigue dit : Viens')))
        ->and(strpos($html, bdcNb('Rodrigue dit : Viens')))->toBeLessThan(strpos($html, bdcNb('Fifamè pense : Je n’en')));
});

it('lit les messages, la voix d’un appareil et la bulle masquée avec leur nature', function (): void {
    $promo = (string) $this->get('/conversations-bd/la-photo-de-la-promo')->assertOk()->getContent();
    expect($promo)->toContain(bdcNb(e('Message reçu de Un membre du groupe : Regardez Grâce qui dort en amphi')))
        ->toContain(bdcNb(e('Message envoyé par Sena : Supprimez la photo')))
        ->toContain(bdcNb(e('Voix au téléphone, Grâce : Merci de m’avoir prévenue.')))
        ->toContain(bdcNb(e('Sena pense : Elle ne sait sûrement pas. Moi, je détesterais ça. Si je dis quelque chose')));

    $haine = (string) $this->get('/conversations-bd/ne-pas-repondre-a-chaud')->assertOk()->getContent();
    expect($haine)->toContain(e(__('conversations_bd.sr.masked')))
        ->toContain('Cette conversation évoque des commentaires haineux')
        ->toContain(bdcNb(e('Sena crie : J’ai envie de leur répondre')));
});

it('affiche l’image d’une case quand le fichier existe', function (): void {
    $conversation = BdConversation::query()->where('model_key', 'm01')->firstOrFail();
    $conversation->panels()->firstOrFail()->scene->forceFill(['image_path' => 'assets/images/xomiton/covers/og-default.jpg'])->save();

    $html = (string) $this->get('/conversations-bd/non-cest-non')->assertOk()->getContent();
    expect(substr_count($html, 'class="xm-bdc-fallback"'))->toBe(4)
        ->and($html)->toContain('<img class="xm-bdc-img" src="'.asset('assets/images/xomiton/covers/og-default.jpg').'"')
        ->toContain('fetchpriority="high"');
});

it('répond 404 neutre à une conversation non publiée, refusée, retirée ou inconnue', function (string $status): void {
    BdConversation::query()->where('model_key', 'm01')->update(['status' => $status]);

    $this->get('/conversations-bd/non-cest-non')->assertNotFound();
    $this->get('/conversations-bd/non-cest-non/seance')->assertNotFound();
})->with(['brouillon', 'proposee', 'refusee', 'retiree']);

it('répond 404 à une adresse inconnue', function (): void {
    $this->get('/conversations-bd/inconnue')->assertNotFound();
});

it('sert le mode séance : jamais indexé, pensées repérables, questions et message clé à la fin', function (): void {
    $html = (string) $this->get('/conversations-bd/non-cest-non/seance')->assertOk()->getContent();

    expect($html)->toContain('noindex')
        ->toContain("x-data='xmBdSeance(")
        ->toContain('"thoughts":[true,true,false,true,false]')
        ->toContain('xm-bdc-chain xm-bdc-thought')
        ->toContain('xm-bdc-thought-line')
        ->toContain(e(__('pages/conversations_bd.seance.reveal')))
        ->toContain(e(__('pages/conversations_bd.seance.questions_title')))
        ->toContain('Comment dire non en une seule phrase')
        ->toContain('Un non n’a pas besoin de justification')
        ->and(substr_count($html, 'data-xm-seance-step'))->toBe(6);
});

it('respecte le mode discret d’une personne connectée : titre neutre et cases repliées', function (): void {
    $user = User::factory()->create();
    Profile::query()->updateOrCreate(['user_id' => $user->id], ['discreet_mode' => true]);
    UserSetting::query()->updateOrCreate(['user_id' => $user->id], ['neutral_tab_title' => true]);

    $html = (string) $this->actingAs($user->fresh())->get('/conversations-bd/non-cest-non')->assertOk()->getContent();

    expect($html)->toContain('<title>'.e(__('seo.noindex.neutral')))
        ->toContain('<details class="card">')
        ->toContain(e(__('pages/conversations_bd.discreet_show')))
        ->toContain('noindex');
});

it('ajoute la liste et les conversations publiées au plan du site, jamais un brouillon ni le mode séance', function (): void {
    config(['app.env' => 'production', 'seo.allow_indexing' => true]);
    SitemapBuilder::flush();

    $locs = collect(app(SitemapBuilder::class)->urls())->pluck('loc');
    $root = rtrim((string) config('app.url'), '/');

    expect($locs)->toContain($root.'/conversations-bd', $root.'/conversations-bd/non-cest-non')
        ->not->toContain($root.'/conversations-bd/les-regles-du-groupe', $root.'/conversations-bd/non-cest-non/seance');
});

it('relie la liste depuis le pied de page public', function (): void {
    expect((string) $this->get('/')->assertOk()->getContent())->toContain(route('conversations-bd.index'));
});
