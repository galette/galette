-- Add amount to payment types
ALTER TABLE galette_types_cotisation ADD amount double NULL;
-- Add region to members
ALTER TABLE galette_adherents ADD region_adh varchar(200) NOT NULL DEFAULT '';
