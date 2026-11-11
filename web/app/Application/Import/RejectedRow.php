<?php

declare(strict_types=1);

namespace App\Application\Import;

use RuntimeException;

/**
 * Ligne du prototype refusée par un importeur : le message est la raison journalisée
 * (audit_logs `import.rejected` + storage/logs/import-prototype.log). L'import continue.
 */
final class RejectedRow extends RuntimeException {}
