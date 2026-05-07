CREATE TABLE dbo.AudraWeb_Eleve_Acces (
    id INT IDENTITY(1,1) PRIMARY KEY,
    login NVARCHAR(100) NOT NULL,
    password_hash NVARCHAR(255) NOT NULL,
    student_name NVARCHAR(255) NULL,
    email NVARCHAR(100) NULL,
    numero_cours NVARCHAR(40) NOT NULL,
    is_active BIT NOT NULL DEFAULT 1,
    reset_token_hash VARCHAR(64) NULL,
    reset_token_expires_at DATETIME NULL
);
