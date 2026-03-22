-- RBAC Migration for Galette
-- Author: Antigravity AI
-- Date: 2026-03-22

-- Permissions table
CREATE TABLE IF NOT EXISTS galette_permissions (
    id_perm int unsigned NOT NULL auto_increment,
    nom_perm varchar(255) NOT NULL,
    description_perm longtext,
    PRIMARY KEY (id_perm),
    UNIQUE (nom_perm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Roles table
CREATE TABLE IF NOT EXISTS galette_roles (
    id_role int unsigned NOT NULL auto_increment,
    nom_role varchar(255) NOT NULL,
    PRIMARY KEY (id_role),
    UNIQUE (nom_role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Role-Permissions mapping
CREATE TABLE IF NOT EXISTS galette_role_permissions (
    id_role int unsigned NOT NULL,
    id_perm int unsigned NOT NULL,
    PRIMARY KEY (id_role, id_perm),
    FOREIGN KEY (id_role) REFERENCES galette_roles (id_role) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_perm) REFERENCES galette_permissions (id_perm) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- User-Roles mapping (with optional context/group)
CREATE TABLE IF NOT EXISTS galette_adherent_roles (
    id_adh_role int unsigned NOT NULL auto_increment,
    id_adh int unsigned NOT NULL,
    id_role int unsigned NOT NULL,
    id_group int unsigned DEFAULT NULL,
    PRIMARY KEY (id_adh_role),
    UNIQUE KEY (id_adh, id_role, id_group),
    FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_role) REFERENCES galette_roles (id_role) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_group) REFERENCES galette_groups (id_group) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Initial data
INSERT IGNORE INTO galette_permissions (nom_perm, description_perm) VALUES 
('member:read', 'Voir les adhérents'),
('member:write', 'Créer ou modifier les adhérents'),
('member:delete', 'Supprimer les adhérents'),
('contribution:read', 'Voir les cotisations et transactions'),
('contribution:write', 'Gérer les cotisations et transactions'),
('group:read', 'Voir les groupes'),
('group:write', 'Gérer les groupes'),
('configuration:all', 'Toute la configuration');

INSERT IGNORE INTO galette_roles (nom_role) VALUES 
('Administrateur'), 
('Secrétaire'), 
('Trésorier'),
('Responsable de groupe');

-- Assign all to Administrateur
INSERT IGNORE INTO galette_role_permissions (id_role, id_perm) 
SELECT r.id_role, p.id_perm FROM galette_roles r, galette_permissions p 
WHERE r.nom_role = 'Administrateur';

-- Assign member:read, member:write to Secrétaire
INSERT IGNORE INTO galette_role_permissions (id_role, id_perm)
SELECT r.id_role, p.id_perm FROM galette_roles r, galette_permissions p
WHERE r.nom_role = 'Secrétaire' AND p.nom_perm IN ('member:read', 'member:write');

-- Assign contribution:read, contribution:write to Trésorier
INSERT IGNORE INTO galette_role_permissions (id_role, id_perm)
SELECT r.id_role, p.id_perm FROM galette_roles r, galette_permissions p
WHERE r.nom_role = 'Trésorier' AND p.nom_perm IN ('contribution:read', 'contribution:write');
