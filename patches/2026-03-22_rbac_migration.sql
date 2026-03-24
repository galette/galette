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
('member:list', 'Lister les adhérents'),
('member:add', 'Ajouter un adhérent'),
('member:read', 'Voir un adhérent'),
('member:edit', 'Modifier un adhérent'),
('member:delete', 'Supprimer un adhérent'),
('member:export', 'Exporter les adhérents'),
('group:list', 'Lister les groupes'),
('group:manage', 'Gérer les groupes (nom, hiérarchie)'),
('group:assign', 'Affecter des membres aux groupes'),
('contribution:list', 'Lister les cotisations'),
('contribution:read', 'Voir une cotisation'),
('contribution:add', 'Ajouter une cotisation'),
('contribution:edit', 'Modifier une cotisation'),
('contribution:stats', 'Voir les statistiques de cotisations'),
('config:read', 'Lire la configuration'),
('config:write', 'Modifier la configuration'),
('system:update', 'Mettre à jour Galette'),
('system:logs', 'Voir les journaux système'),
('system:backup', 'Gérer les sauvegardes');

INSERT IGNORE INTO galette_roles (nom_role) VALUES 
('Administrateur'), 
('Secrétaire'), 
('Trésorier'),
('Responsable de groupe');

-- Assign all to Administrateur
INSERT IGNORE INTO galette_role_permissions (id_role, id_perm) 
SELECT r.id_role, p.id_perm FROM galette_roles r, galette_permissions p 
WHERE r.nom_role = 'Administrateur';

-- Assign member:* to Secrétaire
INSERT IGNORE INTO galette_role_permissions (id_role, id_perm)
SELECT r.id_role, p.id_perm FROM galette_roles r, galette_permissions p
WHERE r.nom_role = 'Secrétaire' AND p.nom_perm LIKE 'member:%';

-- Assign contribution:* to Trésorier
INSERT IGNORE INTO galette_role_permissions (id_role, id_perm)
SELECT r.id_role, p.id_perm FROM galette_roles r, galette_permissions p
WHERE r.nom_role = 'Trésorier' AND p.nom_perm LIKE 'contribution:%';
