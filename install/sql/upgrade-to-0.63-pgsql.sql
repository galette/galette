ALTER TABLE galette_adherents ADD pref_lang character varying(20) DEFAULT 'french'
INSERT INTO galette_types_cotisation VALUES (7, 'Cotisation annuelle (à payer)');
