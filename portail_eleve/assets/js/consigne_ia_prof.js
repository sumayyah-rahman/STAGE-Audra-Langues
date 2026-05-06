// consigne_ia_prof.js
// Gestion de la page consigne_ia_prof.php
// - charge les cours du prof connecté
// - charge les élèves du cours sélectionné
// - enregistre une consigne IA personnalisée pour un élève précis

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

			chargerEleves(idCours);
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