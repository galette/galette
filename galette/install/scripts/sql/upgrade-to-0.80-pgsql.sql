-- Change IP size to handle ipv6 address
ALTER TABLE galette_logs ALTER ip_log TYPE varchar(46);
-- Change labels and translations sizes
ALTER TABLE galette_l10n ALTER text_orig TYPE varchar(100);
ALTER TABLE galette_l10n ALTER text_trans TYPE varchar(100);
ALTER TABLE galette_types_cotisation ALTER libelle_type_cotis TYPE varchar(100);

