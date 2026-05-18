// consigne_ia_prof.js
// Gestion de la page consigne_ia_prof.php
// - charge les cours du prof connecté
// - charge les élèves du cours sélectionné
// - enregistre une consigne IA personnalisée pour un élève précis
function escapeHtml(str) {
	return String(str || '')
		.replace('&', '&amp;')
		.replace('<', '&lt;')
		.replace('>', '&gt;')
		.replace('"', '&quot;')
		.replace("'", '&apos;')
}

async function chargerCours() {
	const sel = document.getElementById('cours');
	const msg = document.getElementById('msg');

	if (!sel) return;

	sel.innerHTML = '<option value="">-- Sélectionnez un cours --</option>';

	try {
		const res = await fetch('./select_cours_eleve_ia.php?action=cours', {
			cache: 'no-store',
			credentials: 'include'
		});

		const js = await res.json();

		if (!js || js.success !== true || !Array.isArray(js.cours)) {
			if (msg) {
				msg.style.color = '#b91c1c';
				msg.textContent = "⚠️ Impossible de charger la liste des cours.";
			}
			return;
		}

		js.cours.forEach(c => {
			const opt = document.createElement('option');

			opt.value = c.id_cours;

			// On affiche uniquement le numéro de cours.
			// Les élèves seront affichés dans le dropdown élève.
			opt.textContent = c.id_cours;

			// On garde quand même les noms élèves en data-search pour un futur filtre.
			opt.dataset.search = (String(c.id_cours || '') + ' ' + String(c.eleve || '')).toLowerCase();

			sel.appendChild(opt);
		});

		if (msg) {
			msg.style.color = '#166534';
			msg.textContent = '';
		}

	} catch (e) {
		console.error(e);

		if (msg) {
			msg.style.color = '#b91c1c';
			msg.textContent = "⚠️ Erreur réseau pendant le chargement des cours.";
		}
	}
}

async function chargerEleves(idCours) {
	const eleveSelect = document.getElementById('eleve');
	const msg = document.getElementById('msg');

	if (!eleveSelect) return;

	eleveSelect.innerHTML = '<option value="">-- Chargement des élèves... --</option>';

	if (!idCours) {
		eleveSelect.innerHTML = '<option value="">-- Sélectionnez un élève --</option>';
		return;
	}

	try {
		const res = await fetch('./select_cours_eleve_ia.php?action=eleves&id_cours=' + encodeURIComponent(idCours), {
			cache: 'no-store',
			credentials: 'include'
		});

		const js = await res.json();

		if (!js || js.success !== true || !Array.isArray(js.eleves)) {
			eleveSelect.innerHTML = '<option value="">-- Aucun élève trouvé --</option>';

			if (msg) {
				msg.style.color = '#b91c1c';
				msg.textContent = "⚠️ Aucun élève trouvé pour ce cours.";
			}

			return;
		}

		eleveSelect.innerHTML = '<option value="">-- Sélectionnez un élève --</option>';

		if (js.eleves.length === 0) {
			eleveSelect.innerHTML = '<option value="">-- Aucun élève trouvé --</option>';

			if (msg) {
				msg.style.color = '#b91c1c';
				msg.textContent = "⚠️ Aucun élève trouvé pour ce cours.";
			}

			return;
		}

		js.eleves.forEach(e => {
			const opt = document.createElement('option');

			const idAcces = parseInt(e.id_acces || 0, 10);
			const nomEleve = e.nom_eleve || '';

			opt.value = String(idAcces);
			opt.dataset.nomEleve = nomEleve;

			if (idAcces > 0) {
				opt.textContent = nomEleve;
			} else {
				opt.textContent = nomEleve + ' — compte portail non trouvé';
				opt.disabled = true;
			}

			eleveSelect.appendChild(opt);
		});

		if (msg) {
			msg.style.color = '#166534';
			msg.textContent = '';
		}

	} catch (e) {
		console.error(e);

		eleveSelect.innerHTML = '<option value="">-- Erreur de chargement --</option>';

		if (msg) {
			msg.style.color = '#b91c1c';
			msg.textContent = "⚠️ Erreur réseau pendant le chargement des élèves.";
		}
	}
}

async function chargerBilanEleve(idAcces) {
	const bloc = document.getElementById('bilan-ia-eleve');
	const contenu = document.getElementById('bilan-ia-contenu');
	const msg = document.getElementById('msg');

	if (!bloc || !contenu) return;

	bloc.style.display = 'block';
	contenu.innerHTML = '⏳ Chargement du bilan IA...';

	const id = parseInt(idAcces || '0', 10);

	if (id <= 0) {
		contenu.innerHTML = '<p>Aucun compte élève valide sélectionné.</p>';
		return;
	}

	try {
		const res = await fetch('./get_bilan_ia_eleve.php?id_acces=' + encodeURIComponent(String(id)), {
			cache: 'no-store',
			credentials: 'include'
		});

		const js = await res.json();

		if (!js || js.success !== true || !Array.isArray(js.sessions)) {
			contenu.innerHTML = '<p>⚠️ Impossible de charger le bilan IA de cet élève.</p>';

			if (msg) {
				msg.style.color = '#b91c1c';
				msg.textContent = js && js.error ? js.error : "Impossible de charger le bilan IA.";
			}

			return;
		}

		if (js.sessions.length === 0) {
			contenu.innerHTML = '<p>Aucune séance IA terminée pour cet élève pour le moment.</p>';
			return;
		}

		contenu.innerHTML = '';

		js.sessions.forEach(s => {
			const card = document.createElement('div');
			card.style.border = '1px solid #e5e7eb';
			card.style.borderRadius = '10px';
			card.style.padding = '12px';
			card.style.marginBottom = '12px';
			card.style.background = '#ffffff';

			const dateSession = escapeHtml(s.date_session || 'Date non précisée');
			const theme = escapeHtml(s.theme || 'Aucun thème');
			const grammar = escapeHtml(s.grammar || 'Aucun point de grammaire');
			const langue = escapeHtml(s.langue_etudiee || '');
			const observation = escapeHtml(s.observation || '');
			const pointsForts = escapeHtml(s.points_forts || '');
			const pointsFaibles = escapeHtml(s.points_faibles || '');
			const pointARenforcer = escapeHtml(s.point_a_renforcer || '');
			const exemple = escapeHtml(s.exemple_a_retravailler || '');

			card.innerHTML = `
				<div style="font-weight:700; color:#2563eb; margin-bottom:8px;">
					${dateSession}${langue ? ' — ' + langue : ''}
				</div>

				<div style="font-size:13px; color:#4b5563; margin-bottom:10px;">
					<strong>Thème :</strong> ${theme}<br>
					<strong>Grammaire :</strong> ${grammar}
				</div>

				<div style="white-space:pre-line; line-height:1.5;">
					<strong>Observation</strong><br>${observation || '—'}<br><br>
					<strong>Points forts</strong><br>${pointsForts || '—'}<br><br>
					<strong>Points faibles</strong><br>${pointsFaibles || '—'}<br><br>
					<strong>Point à renforcer</strong><br>${pointARenforcer || '—'}<br><br>
					<strong>Exemple / correction à retravailler</strong><br>${exemple || '—'}
				</div>
			`;

			contenu.appendChild(card);
		});

		if (msg) {
			msg.textContent = '';
		}

	} catch (e) {
		console.error(e);
		contenu.innerHTML = '<p>⚠️ Erreur réseau pendant le chargement du bilan IA.</p>';

		if (msg) {
			msg.style.color = '#b91c1c';
			msg.textContent = "Erreur réseau pendant le chargement du bilan IA.";
		}
	}
}

async function envoyerConsigne() {
	const coursSelect = document.getElementById('cours');
	const eleveSelect = document.getElementById('eleve');
	const consigneInput = document.getElementById('consigne');
	const msg = document.getElementById('msg');

	const numeroCours = coursSelect ? coursSelect.value.trim() : '';
	const idAcces = eleveSelect ? parseInt(eleveSelect.value || '0', 10) : 0;

	const selectedEleveOption = eleveSelect
		? eleveSelect.options[eleveSelect.selectedIndex]
		: null;

	const eleve = selectedEleveOption
		? (selectedEleveOption.dataset.nomEleve || '')
		: '';

	const consigne = consigneInput ? consigneInput.value.trim() : '';

	if (!numeroCours || idAcces <= 0 || !eleve || !consigne) {
		if (msg) {
			msg.style.color = '#b91c1c';
			msg.textContent = "⚠️ Veuillez sélectionner un cours, un élève et saisir une consigne.";
		}
		return;
	}

	if (msg) {
		msg.style.color = '#111827';
		msg.textContent = "⏳ Enregistrement en cours...";
	}

	try {
		const res = await fetch('./save_consigne_ia_prof.php', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json'
			},
			credentials: 'include',
			body: JSON.stringify({
				id_acces: idAcces,
				numero_cours: numeroCours,
				eleve: eleve,
				consigne_ia: consigne
			})
		});

		const js = await res.json();

		if (!js || js.success !== true) {
			if (msg) {
				msg.style.color = '#b91c1c';
				msg.textContent = "❌ " + ((js && js.error) ? js.error : "Erreur lors de l’enregistrement.");
			}
			return;
		}

		if (msg) {
			msg.style.color = '#166534';
			msg.textContent = "✅ Consigne IA enregistrée pour " + eleve + ".";
		}

		if (consigneInput) {
			consigneInput.value = '';
		}

	} catch (e) {
		console.error(e);

		if (msg) {
			msg.style.color = '#b91c1c';
			msg.textContent = "❌ Erreur réseau lors de l’enregistrement.";
		}
	}
}

document.addEventListener('DOMContentLoaded', () => {
	chargerCours();

	const coursSelect = document.getElementById('cours');

	if (coursSelect) {
		coursSelect.addEventListener('change', (e) => {
			const idCours = e.target.value;

			const eleveSelect = document.getElementById('eleve');
			if (eleveSelect) {
				eleveSelect.innerHTML = '<option value="">-- Sélectionnez un élève --</option>';
			}

			const bloc = document.getElementById('bilan-ia-eleve');
			const contenu = document.getElementById('bilan-ia-contenu');

			if (bloc) bloc.style.display = 'none';
			if (contenu) contenu.innerHTML = 'Sélectionnez un élève pour afficher son bilan IA.';

			chargerEleves(idCours);
		});
	}

	const eleveSelect = document.getElementById('eleve');

	if (eleveSelect) {
		eleveSelect.addEventListener('change', (e) => {
			const idAcces = e.target.value;

			if (idAcces) {
				chargerBilanEleve(idAcces);
			} else {
				const bloc = document.getElementById('bilan-ia-eleve');
				const contenu = document.getElementById('bilan-ia-contenu');

				if (bloc) bloc.style.display = 'none';
				if (contenu) contenu.innerHTML = 'Sélectionnez un élève pour afficher son bilan IA.';
			}
		});
	}

	const form = document.getElementById('consigne-form');

	if (form) {
		form.addEventListener('submit', async (e) => {
			e.preventDefault();
			await envoyerConsigne();
		});
	}
});