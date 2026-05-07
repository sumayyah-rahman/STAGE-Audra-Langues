CREATE TABLE dbo.AudraWeb_Eleve_Suivi_IA (
    id INT IDENTITY(1,1) PRIMARY KEY,
    id_acces INT NOT NULL,
    last_theme NVARCHAR(100) NULL,
    last_grammar NVARCHAR(100) NULL,
    last_session_date DATETIME NOT NULL DEFAULT GETDATE(),
    point_a_renforcer NVARCHAR(255) NULL,
    progression_note NVARCHAR(255) NULL,
    FOREIGN KEY (id_acces)
        REFERENCES dbo.AudraWeb_Eleve_Acces(id)
);
