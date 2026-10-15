<?php $mu = x_user(); ?>
<!-- Modal : créer une publication -->
<div class="modal fade" id="modalCreateFeed" tabindex="-1" aria-labelledby="modalLabelCreateFeed" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
		<div class="modal-content">
			<form action="<?= BASE ?>post-create.php" method="post" enctype="multipart/form-data">
				<?= csrf_field() ?>
				<div class="modal-header">
					<h5 class="modal-title" id="modalLabelCreateFeed">Créer une publication</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
				</div>
				<div class="modal-body">
					<div class="d-flex mb-3">
						<div class="avatar avatar-xs me-2"><img class="avatar-img rounded-circle" src="<?= x_avatar($mu['avatar'] ?? 0) ?>" alt="Votre avatar" title="Votre avatar"></div>
						<div class="w-100">
							<textarea class="form-control pe-4 fs-5 lh-1 border-0" name="text" rows="4" placeholder="Partagez vos pensées, une question, un témoignage…" required></textarea>
						</div>
					</div>
					<div class="row g-2 mb-2">
						<div class="col-sm-6">
							<label class="form-label small mb-1">Type de publication</label>
							<select class="form-select form-select-sm" name="tag">
								<option>Témoignage</option><option>Question</option><option>Ressource</option><option>Événement</option>
								<option value="Violences">Violences (sensibilisation)</option>
							</select>
						</div>
						<div class="col-sm-6">
							<label class="form-label small mb-1">Visibilité</label>
							<select class="form-select form-select-sm" name="visibility">
								<option value="public">Communauté</option><option value="friends">Abonné·es</option><option value="me">Moi seulement</option>
							</select>
						</div>
					</div>
					<div class="mb-2">
						<label class="form-label small mb-1"><i class="bi bi-image-fill text-success me-1"></i>Photo (facultatif)</label>
						<input class="form-control form-control-sm" type="file" name="photo" accept="image/*">
					</div>
					<div class="form-check form-switch small">
						<input class="form-check-input" type="checkbox" role="switch" id="postAnon" name="anonymous" value="1">
						<label class="form-check-label" for="postAnon">Publier sous pseudonyme (anonyme)</label>
					</div>
					<div class="alert alert-warning small mt-3 mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Besoin d’aide ? Utilisez plutôt <a href="<?= BASE ?>signalement.php" class="alert-link">le signalement anonyme</a> ou <a href="<?= BASE ?>sos.php" class="alert-link">SOS</a>.</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
					<button type="submit" class="btn btn-primary">Publier</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- Modal : photo (compat. pages Social) -->
<div class="modal fade" id="feedActionPhoto" tabindex="-1" aria-labelledby="feedActionPhotoLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<form action="<?= BASE ?>post-create.php" method="post" enctype="multipart/form-data">
				<?= csrf_field() ?><input type="hidden" name="tag" value="Témoignage">
				<div class="modal-header">
					<h5 class="modal-title" id="feedActionPhotoLabel">Ajouter une photo</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
				</div>
				<div class="modal-body">
					<textarea class="form-control mb-3" name="text" rows="2" placeholder="Légende" required></textarea>
					<input class="form-control" type="file" name="photo" accept="image/*" required>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-danger-soft me-2" data-bs-dismiss="modal">Annuler</button>
					<button type="submit" class="btn btn-success-soft">Publier</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- Modal : vidéo / témoignage audio (compat.) -->
<div class="modal fade" id="feedActionVideo" tabindex="-1" aria-labelledby="feedActionVideoLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="feedActionVideoLabel">Partager une vidéo</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
			</div>
			<div class="modal-body">
				<p class="small text-secondary">Les vidéos sont modérées avant publication afin de protéger les personnes qui apparaissent à l’écran.</p>
				<input class="form-control" type="file" accept="video/*">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-danger-soft me-2" data-bs-dismiss="modal">Annuler</button>
				<button type="button" class="btn btn-success-soft" data-bs-dismiss="modal" onclick="XM.toast('Vidéo envoyée en modération')">Envoyer</button>
			</div>
		</div>
	</div>
</div>

<!-- Modal : créer un événement -->
<div class="modal fade" id="modalCreateEvents" tabindex="-1" aria-labelledby="modalLabelCreateEvent" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<form action="<?= BASE ?>events.php" method="post">
				<?= csrf_field() ?><input type="hidden" name="action" value="create">
				<div class="modal-header">
					<h5 class="modal-title" id="modalLabelCreateEvent">Créer un événement</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3"><label class="form-label">Titre</label><input type="text" name="title" class="form-control" placeholder="Atelier, causerie, séance de sensibilisation…" required></div>
					<div class="row g-3 mb-3">
						<div class="col-sm-6"><label class="form-label">Date</label><input type="date" name="date" class="form-control" required></div>
						<div class="col-sm-6"><label class="form-label">Heure</label><input type="time" name="time" class="form-control" value="15:00"></div>
					</div>
					<div class="mb-3"><label class="form-label">Lieu ou lien</label><input type="text" name="place" class="form-control" placeholder="Cotonou, Porto-Novo… ou en ligne"></div>
					<div class="mb-0"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-danger-soft me-2" data-bs-dismiss="modal">Annuler</button>
					<button type="submit" class="btn btn-success-soft">Créer l’événement</button>
				</div>
			</form>
		</div>
	</div>
</div>
