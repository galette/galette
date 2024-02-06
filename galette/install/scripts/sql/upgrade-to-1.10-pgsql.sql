-- Add amount to payment types
ALTER TABLE galette_types_cotisation ADD amount real DEFAULT '0';
-- Add region to members
ALTER TABLE galette_adherents ADD region_adh character varying(200) DEFAULT '' NOT NULL;
