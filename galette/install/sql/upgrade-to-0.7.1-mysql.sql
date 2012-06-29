
-- Update fields length
ALTER TABLE galette_adherents CHANGE nom_adh nom_adh varchar(50) NOT NULL default '';
ALTER TABLE galette_adherents CHANGE prenom_adh prenom_adh varchar(50) default NULL;
ALTER TABLE galette_adherents CHANGE societe_adh societe_adh varchar(200) default NULL;
ALTER TABLE galette_transactions CHANGE trans_desc trans_desc varchar(150) NOT NULL default '';
