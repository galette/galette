-- Change IP size to handle ipv6 address
ALTER TABLE galette_logs CHANGE ip_log ip_log varchar(46) NOT NULL DEFAULT '';
-- Change labels and translations sizes
ALTER TABLE galette_l10n CHANGE text_orig text_orig VARCHAR(100) NOT NULL;
ALTER TABLE galette_l10n DROP PRIMARY KEY, ADD PRIMARY KEY (text_orig, text_locale);
ALTER TABLE galette_types_cotisation CHANGE libelle_type_cotis libelle_type_cotis VARCHAR(100) NOT NULL DEFAULT '';
