-- Base data for fresh install

INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_admin_login', 'admin');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_admin_pass', '21232f297a57a5a743894a0e4a801fc3');

INSERT INTO galette_preferences (nom_pref,val_pref) VALUES ('pref_nom','galette');
INSERT INTO galette_preferences (nom_pref,val_pref) VALUES ('pref_adresse','-');
INSERT INTO galette_preferences (nom_pref,val_pref) VALUES ('pref_adresse2','');
INSERT INTO galette_preferences (nom_pref,val_pref) VALUES ('pref_cp','-');
INSERT INTO galette_preferences (nom_pref,val_pref) VALUES ('pref_ville','-');
INSERT INTO galette_preferences (nom_pref,val_pref) VALUES ('pref_pays','-');
INSERT INTO galette_preferences (nom_pref,val_pref) VALUES ('pref_lang','french');
INSERT INTO galette_preferences (nom_pref,val_pref) VALUES ('pref_numrows','30');
INSERT INTO galette_preferences (nom_pref,val_pref) VALUES ('pref_log','2');

INSERT INTO galette_preferences (nom_pref,val_pref) VALUES ('pref_email_nom','galette');
INSERT INTO galette_preferences (nom_pref,val_pref) VALUES ('pref_email','mail@domain.com');
INSERT INTO galette_preferences (nom_pref,val_pref) VALUES ('pref_mail_method','0');
INSERT INTO galette_preferences (nom_pref,val_pref) VALUES ('pref_mail_smtp','');
INSERT INTO galette_preferences (nom_pref,val_pref) VALUES ('pref_membership_ext','12');
INSERT INTO galette_preferences (nom_pref,val_pref) VALUES ('pref_beg_membership','');
INSERT INTO galette_preferences (nom_pref,val_pref) VALUES ('pref_email_reply_to','');
INSERT INTO galette_preferences (nom_pref,val_pref) VALUES ('pref_website','');

INSERT INTO galette_preferences (nom_pref,val_pref) VALUES ('pref_etiq_marges_v','10');
INSERT INTO galette_preferences (nom_pref,val_pref) VALUES ('pref_etiq_marges_h','10');
INSERT INTO galette_preferences (nom_pref,val_pref) VALUES ('pref_etiq_hspace','10');
INSERT INTO galette_preferences (nom_pref,val_pref) VALUES ('pref_etiq_vspace','5');
INSERT INTO galette_preferences (nom_pref,val_pref) VALUES ('pref_etiq_hsize','90');
INSERT INTO galette_preferences (nom_pref,val_pref) VALUES ('pref_etiq_vsize','35');
INSERT INTO galette_preferences (nom_pref,val_pref) VALUES ('pref_etiq_cols','2');
INSERT INTO galette_preferences (nom_pref,val_pref) VALUES ('pref_etiq_rows','7');
INSERT INTO galette_preferences (nom_pref,val_pref) VALUES ('pref_etiq_corps','12');

-- Add card preferences;
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_card_abrev', 'GALETTE');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_card_strip','Gestion d Adherents en Ligne Extrêmement Tarabiscoté');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_card_tcol', 'FFFFFF');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_card_scol', '8C2453');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_card_bcol', '53248C');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_card_hcol', '248C53');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_bool_display_title', '');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_card_address', '1');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_card_year', '2007');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_card_marges_v', '15');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_card_marges_h', '20');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_card_vspace', '5');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_card_hspace', '10');

-- Contribution types
INSERT INTO galette_types_cotisation (id_type_cotis,libelle_type_cotis,cotis_extension) VALUES (1, 'annual fee', '1');
INSERT INTO galette_types_cotisation (id_type_cotis,libelle_type_cotis,cotis_extension) VALUES (2, 'reduced annual fee', '1');
INSERT INTO galette_types_cotisation (id_type_cotis,libelle_type_cotis,cotis_extension) VALUES (3, 'company fee', '1');
INSERT INTO galette_types_cotisation (id_type_cotis,libelle_type_cotis,cotis_extension) VALUES (4, 'donation in kind', null);
INSERT INTO galette_types_cotisation (id_type_cotis,libelle_type_cotis,cotis_extension) VALUES (5, 'donation in money', null);
INSERT INTO galette_types_cotisation (id_type_cotis,libelle_type_cotis,cotis_extension) VALUES (6, 'partnership', null);
INSERT INTO galette_types_cotisation (id_type_cotis,libelle_type_cotis,cotis_extension) VALUES (7, 'annual fee (to be paid)', '1');

-- Member types
INSERT INTO galette_statuts (id_statut,libelle_statut,priorite_statut) VALUES (1, 'President',0);
INSERT INTO galette_statuts (id_statut,libelle_statut,priorite_statut) VALUES (2, 'Treasurer',10);
INSERT INTO galette_statuts (id_statut,libelle_statut,priorite_statut) VALUES (3, 'Secretary',20);
INSERT INTO galette_statuts (id_statut,libelle_statut,priorite_statut) VALUES (4, 'Active member',30);
INSERT INTO galette_statuts (id_statut,libelle_statut,priorite_statut) VALUES (5, 'Benefactor member',40);
INSERT INTO galette_statuts (id_statut,libelle_statut,priorite_statut) VALUES (6, 'Founder member',50);
INSERT INTO galette_statuts (id_statut,libelle_statut,priorite_statut) VALUES (7, 'Old-timer',60);
INSERT INTO galette_statuts (id_statut,libelle_statut,priorite_statut) VALUES (8, 'Society',70);
INSERT INTO galette_statuts (id_statut,libelle_statut,priorite_statut) VALUES (9, 'Non-member',80);
INSERT INTO galette_statuts (id_statut,libelle_statut,priorite_statut) VALUES (10, 'Vice-president',5);
