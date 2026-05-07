CREATE TABLE dbo.AudraWeb_Eleve_Consigne (
    id INT IDENTITY(1,1) PRIMARY KEY,
    id_acces INT NULL,
    numero_cours NVARCHAR(40) NOT NULL,
    eleve NVARCHAR(255) NOT NULL,
    consigne_ia NVARCHAR(MAX) NOT NULL,
    prof NVARCHAR(255) NULL,
    prof_code NVARCHAR(40) NULL,
    date_creation DATETIME NOT NULL DEFAULT GETDATE(),
    is_active BIT NOT NULL DEFAULT 1,
    FOREIGN KEY (id_acces)
        REFERENCES dbo.AudraWeb_Eleve_Acces(id)
);
