<?php

declare(strict_types=1);

namespace App\Console\Commands\Feed;

use App\Application\Feed\ReviewVideo;
use App\Console\Commands\Concerns\FormatsCounts;
use Illuminate\Console\Command;

/**
 * Q96 : supprime du disque privé les fichiers des vidéos refusées par la modération depuis plus de
 * `xomiton.feed.video_rejected_retention_days` jours (7 par défaut). La ligne `post_media` reste, sans chemin. Planifiée
 * chaque jour à 03:30 (routes/console.php, purges de fichiers).
 */
class PurgeRejectedVideosCommand extends Command
{
    use FormatsCounts;

    protected $signature = 'videos:purge-rejected';

    protected $description = 'Supprime les fichiers des vidéos refusées à la relecture après le délai de conservation';

    public function handle(ReviewVideo $review): int
    {
        $this->info($this->countLabel($review->purgeRejected(), 'fichier vidéo supprimé', 'fichiers vidéo supprimés').'.');

        return self::SUCCESS;
    }
}
