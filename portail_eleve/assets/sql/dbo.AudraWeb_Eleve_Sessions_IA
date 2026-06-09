CREATE TABLE dbo.AudraWeb_Eleve_Sessions_IA (
    id INT IDENTITY(1,1) PRIMARY KEY,
    id_acces INT NOT NULL,
    numero_cours NVARCHAR(40) NULL,
    eleve NVARCHAR(255) NULL,
    theme NVARCHAR(255) NULL,
    grammar NVARCHAR(255) NULL,
    langue_etudiee NVARCHAR(100) NULL,
    observation NVARCHAR(MAX) NULL,
    points_forts NVARCHAR(MAX) NULL,
    points_faibles NVARCHAR(MAX) NULL,
    point_a_renforcer NVARCHAR(MAX) NULL,
    exemple_a_retravailler NVARCHAR(MAX) NULL,
    session_history NVARCHAR(MAX) NULL,
    date_session DATETIME NOT NULL DEFAULT GETDATE(),
    FOREIGN KEY (id_acces)
        REFERENCES dbo.AudraWeb_Eleve_Acces(id)
);
