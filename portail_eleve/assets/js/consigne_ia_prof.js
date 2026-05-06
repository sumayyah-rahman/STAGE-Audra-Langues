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

async function chargerEleves(idCours) {
    const eleveSelect = document.getElementById('eleve');
    const msg = document.getElementById('msg');
    
    if (!eleveSelect) return;
    
    eleveSelect.innerHTML = '<option value="">-- Chargement des élèves... --</option>';
    
    try {
        const res = await fetch(`./select_cours_eleve_ia.php?action=eleves&id_cours=${encodeURIComponent(idCours)}`, {
            cache: 'no-store',
            credentials: 'include'
        });
        
        const js = await res.json();
        
        if (!js || js.success !== true || !Array.isArray(js.eleves)) {
            eleveSelect.innerHTML = '<option value="">-- Aucun élève trouvé --</option>';
            if (msg) msg.textContent = "⚠️ Aucun élève trouvé pour ce cours.";
            return;
        }
        
        eleveSelect.innerHTML = '<option value="">-- Sélectionnez un élève --</option>';
        
        js.eleves.forEach(e => {
            const opt = document.createElement('option');
            opt.value = e.nom_eleve;
            opt.textContent = e.nom_eleve;
            eleveSelect.appendChild(opt);
        });
        
        if (msg) msg.textContent = '';
        
    } catch (e) {
        console.error(e);
        eleveSelect.innerHTML = '<option value="">-- Erreur de chargement --</option>';
        if (msg) msg.textContent = "⚠️ Erreur réseau pendant le chargement des élèves.";
    }
}

document.addEventListener('DOMContentLoaded', () => {
    chargerCours();
    
    // TAMBAH INI
    const coursSelect = document.getElementById('cours');
    if (coursSelect) {
        coursSelect.addEventListener('change', (e) => {
            const idCours = e.target.value;
            if (idCours) {
                chargerEleves(idCours);
            } else {
                const eleveSelect = document.getElementById('eleve');
                if (eleveSelect) {
                    eleveSelect.innerHTML = '<option value="">-- Sélectionnez un élève --</option>';
                }
            }
        });
    }
    
    const form = document.getElementById('consigne-form');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const idCours = document.getElementById('cours')?.value;
            const eleve = document.getElementById('eleve')?.value;
            const consigne = document.getElementById('consigne')?.value;
            const msg = document.getElementById('msg');
            
            if (!idCours || !eleve || !consigne) {
                if (msg) msg.textContent = "⚠️ Veuillez remplir tous les champs.";
                return;
            }
            
            if (msg) msg.textContent = "Envoi en cours...";
            
            try {
                const res = await fetch('./save_consigne_ia_prof.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        numero_cours: idCours,
                        eleve: eleve,
                        consigne_ia: consigne
                    })
                });
                
                const js = await res.json();
                
                if (js.success) {
                    msg.innerHTML = "✅ Consigne envoyée avec succès!";
                    document.getElementById('consigne').value = '';
                    // Optional: reset dropdowns
                } else {
                    msg.innerHTML = "❌ Erreur: " + (js.error || "Inconnue");
                }
            } catch (e) {
                msg.innerHTML = "❌ Erreur réseau.";
            }
        });
    }
});