--
-- Copyright © 2003-2026 The Galette Team
--
-- This file is part of Galette (https://galette.eu).
--
-- Galette is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- Galette is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
--  GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with Galette. If not, see <http://www.gnu.org/licenses/>.
--

SELECT setval((SELECT pg_get_serial_sequence('galette_adherents', 'id_adh')), (SELECT max(id_adh) FROM galette_adherents));
SELECT setval((SELECT pg_get_serial_sequence('galette_cotisations', 'id_cotis')), (SELECT max(id_cotis) FROM galette_cotisations));
SELECT setval((SELECT pg_get_serial_sequence('galette_statuts', 'id_statut')), (SELECT max(id_statut) FROM galette_statuts));
SELECT setval((SELECT pg_get_serial_sequence('galette_transactions', 'trans_id')), (SELECT max(trans_id) FROM galette_transactions));
SELECT setval((SELECT pg_get_serial_sequence('galette_preferences', 'id_pref')), (SELECT max(id_pref) FROM galette_preferences));
SELECT setval((SELECT pg_get_serial_sequence('galette_logs', 'id_log')), (SELECT max(id_log) FROM galette_logs));
SELECT setval((SELECT pg_get_serial_sequence('galette_field_types', 'field_id')), (SELECT max(field_id) FROM galette_field_types));
SELECT setval((SELECT pg_get_serial_sequence('galette_types_cotisation', 'id_type_cotis')), (SELECT max(id_type_cotis) FROM galette_types_cotisation));
SELECT setval((SELECT pg_get_serial_sequence('galette_groups', 'id_group')), (SELECT max(id_group) FROM galette_groups));
SELECT setval((SELECT pg_get_serial_sequence('galette_mailing_history', 'mailing_id')), (SELECT max(mailing_id) FROM galette_mailing_history));
SELECT setval((SELECT pg_get_serial_sequence('galette_titles', 'id_title')), (SELECT max(id_title) FROM galette_titles));
SELECT setval((SELECT pg_get_serial_sequence('galette_reminders', 'reminder_id')), (SELECT max(reminder_id) FROM galette_reminders));
SELECT setval((SELECT pg_get_serial_sequence('galette_pdfmodels', 'model_id')), (SELECT max(model_id) FROM galette_pdfmodels));
SELECT setval((SELECT pg_get_serial_sequence('galette_import_model', 'model_id')), (SELECT max(model_id) FROM galette_import_model));
SELECT setval((SELECT pg_get_serial_sequence('galette_paymenttypes', 'type_id')), (SELECT max(type_id) FROM galette_paymenttypes));
SELECT setval((SELECT pg_get_serial_sequence('galette_searches', 'search_id')), (SELECT max(search_id) FROM galette_searches));
SELECT setval((SELECT pg_get_serial_sequence('galette_texts', 'tid')), (SELECT max(tid) FROM galette_texts));
SELECT setval((SELECT pg_get_serial_sequence('galette_fields_categories', 'id_field_category')), (SELECT max(id_field_category) FROM galette_fields_categories));
SELECT setval((SELECT pg_get_serial_sequence('galette_socials', 'id_social')), (SELECT max(id_social) FROM galette_socials));
SELECT setval((SELECT pg_get_serial_sequence('galette_documents', 'id_document')), (SELECT max(id_document) FROM galette_documents));
SELECT setval((SELECT pg_get_serial_sequence('galette_payments_schedules', 'id_schedule')), (SELECT max(id_schedule) FROM galette_payments_schedules));

UPDATE galette_database SET version = 1.201;
