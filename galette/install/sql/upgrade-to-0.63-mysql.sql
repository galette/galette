ALTER TABLE galette_adherents ADD pref_lang varchar(20) default 'french' AFTER date_echeance
INSERT INTO galette_types_cotisation VALUES (7, 'Cotisation annuelle (à payer)');

