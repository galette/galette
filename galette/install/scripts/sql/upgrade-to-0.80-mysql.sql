-- Change IP size to handle ipv6 address
ALTER TABLE galette_logs CHANGE ip_log ip_log varchar(46) NOT NULL DEFAULT '';

