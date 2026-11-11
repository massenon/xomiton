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

<!-- Modal : créer un sondage -->
<div class="modal fade" id="modalCreatePoll" tabindex="-1" aria-labelledby="modalLabelCreatePoll" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
		<div class="modal-content">
			<form action="<?= BASE ?>post-create.php" method="post" enctype="multipart/form-data" data-poll-create novalidate>
				<?= csrf_field() ?><input type="hidden" name="kind" value="poll">
				<div class="modal-header">
					<h5 class="modal-title" id="modalLabelCreatePoll"><i class="bi bi-bar-chart-fill text-info me-2" aria-hidden="true"></i>Créer un sondage</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<label class="form-label" for="pollQuestion">Question (titre du sondage) *</label>
						<input class="form-control" id="pollQuestion" name="question" required minlength="5" maxlength="200" placeholder="Ex. : Quel thème pour le prochain atelier ?" aria-describedby="pollQuestionCount">
						<div class="form-text text-end" id="pollQuestionCount" data-count-for="pollQuestion">0 / 200</div>
						<div class="invalid-feedback">Entre 5 et 200 caractères.</div>
					</div>
					<fieldset class="mb-3">
						<legend class="form-label fs-6 mb-1">Réponses proposées * <span class="small text-secondary">(2 à 6)</span></legend>
						<div class="vstack gap-2" data-poll-options>
							<?php for ($i = 1; $i <= 2; $i++): ?>
							<div class="input-group xm-poll-option-row">
								<span class="input-group-text"><?= $i ?></span>
								<input class="form-control" name="options[]" maxlength="80" required placeholder="Réponse <?= $i ?>" aria-label="Réponse <?= $i ?>">
								<button class="btn btn-light text-danger" type="button" data-poll-remove aria-label="Retirer la réponse <?= $i ?>" disabled><i class="bi bi-x-lg"></i></button>
							</div>
							<?php endfor; ?>
						</div>
						<button type="button" class="btn btn-sm btn-primary-soft mt-2" data-poll-add><i class="bi bi-plus-lg me-1"></i>Ajouter une réponse</button>
						<div class="invalid-feedback d-block d-none" data-poll-options-error>Renseignez au moins 2 réponses différentes.</div>
					</fieldset>
					<div class="mb-3">
						<label class="form-label" for="pollText">Texte d’accompagnement <span class="small text-secondary">(facultatif)</span></label>
						<textarea class="form-control" id="pollText" name="text" rows="2" maxlength="3000" placeholder="Donnez du contexte à votre question…"></textarea>
					</div>
					<div class="mb-3">
						<label class="form-label" for="pollPhoto"><i class="bi bi-image-fill text-success me-1" aria-hidden="true"></i>Image <span class="small text-secondary">(facultatif, 5 Mo max.)</span></label>
						<input class="form-control form-control-sm" type="file" id="pollPhoto" name="photo" accept="image/jpeg,image/png,image/webp,image/gif" data-img-preview="#pollPhotoPreview">
						<img id="pollPhotoPreview" class="img-fluid rounded mt-2 d-none" alt="Aperçu de l’image du sondage" style="max-height:160px">
					</div>
					<div class="row g-2 mb-2">
						<div class="col-sm-6">
							<label class="form-label small mb-1" for="pollDuration">Durée</label>
							<select class="form-select form-select-sm" id="pollDuration" name="duration">
								<option value="1">1 jour</option><option value="3">3 jours</option><option value="7" selected>1 semaine</option><option value="14">2 semaines</option><option value="30">1 mois</option><option value="0">Sans date de fin</option>
							</select>
						</div>
						<div class="col-sm-6">
							<label class="form-label small mb-1" for="pollVisibility">Visibilité</label>
							<select class="form-select form-select-sm" id="pollVisibility" name="visibility"><option value="public">Communauté</option><option value="friends">Abonné·es</option><option value="me">Moi seulement</option></select>
						</div>
					</div>
					<div class="form-check form-switch small"><input class="form-check-input" type="checkbox" role="switch" id="pollMulti" name="multiple" value="1"><label class="form-check-label" for="pollMulti">Autoriser plusieurs réponses</label></div>
					<div class="form-check form-switch small"><input class="form-check-input" type="checkbox" role="switch" id="pollResults" name="results_before_vote" value="1"><label class="form-check-label" for="pollResults">Afficher les résultats avant le vote</label></div>
					<div class="form-check form-switch small"><input class="form-check-input" type="checkbox" role="switch" id="pollAnon" name="anonymous" value="1"><label class="form-check-label" for="pollAnon">Publier sous pseudonyme (anonyme)</label></div>
					<p class="small text-secondary mt-2 mb-0"><i class="bi bi-lock me-1" aria-hidden="true"></i>Les votes sont confidentiels : seuls les totaux sont affichés.</p>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
					<button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Publier le sondage</button>
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
