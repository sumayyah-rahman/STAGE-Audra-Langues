function togglePassword() {
const pwd = document.getElementById("password");
pwd.type = (pwd.type === "password") ? "text" : "password";
}

document.getElementById("loginForm").addEventListener("submit", async (e) => {
e.preventDefault();

const login = document.getElementById("login").value.trim().toLowerCase();
const password = document.getElementById("password").value.trim().toUpperCase();

try {
    // 1) Récupère les utilisateurs (incachable)
    const res = await fetch("get_utilisateurs_colleague.php?_ts=" + Date.now(), { cache: "no-store" });
    if (!res.ok) {
    alert("Erreur HTTP: " + res.status);
    return;
    }

    const txt = await res.text();
    let utilisateurs = [];
    try {
    utilisateurs = JSON.parse(txt);
    } catch (e) {
    console.log("Réponse get_utilisateurs_colleague invalide:", txt);
    alert("Réponse invalide du serveur.");
    return;
    }

    const user = (utilisateurs || []).find(u =>
    (u.login || "").toLowerCase() === login &&
    (u.mdp   || "").toUpperCase() === password
    );
    if (!user) {
    alert("Identifiants incorrects.");
    return;
    }

    // 2) Initialise la session (INCACHABLE + vérif JSON)
    const display = (
    String(user.nom_formateur_sql || "").trim() ||
    `${(user.nom || "").toString().toUpperCase()} ${(user.prenom || "").toString().toUpperCase()}`
    ).trim();

    const codeProf = (user.mdp || user.code || "").toString().toUpperCase();

    // On remonte de /modules/portail_prof/ vers la racine audra_portail_prod
    const ts = Date.now();
    const url = `../../set_session_dev.php?display=${encodeURIComponent(display)}&email=${encodeURIComponent(login)}&code=${encodeURIComponent(codeProf)}&_ts=${ts}`;

    const sess = await fetch(url, {
    method: "GET",
    credentials: "include",
    cache: "no-store",
    });

    let data = null;
    try { data = await sess.json(); } catch (e) {}

    if (!sess.ok || !data || data.ok !== true) {
    console.log("set_session_dev KO:", sess.status, data);
    alert("Erreur session.");
    return;
    }

    console.log("DEBUG set_session_dev =>", data);

    // ✅ Redirection incachable
    window.location.href = "form_prof_intro.php?_ts=" + Date.now();
    return;

} catch (err) {
    console.error("Erreur:", err);
    alert("Erreur serveur ou réseau.");
}
});
