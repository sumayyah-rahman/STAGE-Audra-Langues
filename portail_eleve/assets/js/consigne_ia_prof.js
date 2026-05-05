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
			if (msg) msg.textContent = "⚠️ Impossible de charger la liste des cours.";
			return;
		}
		
		js.cours.forEach(c => {
			const opt = document.createElement('option');

			opt.value = c.id_cours;

			const eleve = c.eleve && c.eleve.trim() !== ''
				? c.eleve
				: 'Élève à préciser';

			opt.textContent = c.id_cours + ' — ' + eleve;

			sel.appendChild(opt);
		});

		if (msg) msg.textContent = '';

	} catch (e) {
		console.error(e);
		if (msg) msg.textContent = "⚠️ Erreur réseau pendant le chargement des cours.";
	}
}

document.addEventListener('DOMContentLoaded', () => {
	chargerCours();
});