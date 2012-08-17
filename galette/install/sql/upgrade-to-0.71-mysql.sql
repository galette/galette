-- NULL cause issues on filtering, see #311
UPDATE galette_adherents SET pseudo_adh = '' WHERE pseudo_adh IS NULL;
UPDATE galette_adherents SET prenom_adh = '' WHERE prenom_adh IS NULL;

-- Update fields length
ALTER TABLE galette_adherents CHANGE nom_adh nom_adh varchar(50) NOT NULL default '';
ALTER TABLE galette_adherents CHANGE prenom_adh prenom_adh varchar(50) NOT NULL default '';
ALTER TABLE galette_adherents CHANGE societe_adh societe_adh varchar(200) default NULL;
ALTER TABLE galette_transactions CHANGE trans_desc trans_desc varchar(150) NOT NULL default '';
ALTER TABLE galette_adherents CHANGE pseudo_adh pseudo_adh varchar(20) NOT NULL default '';

UPDATE galette_database SET version = 0.701;
