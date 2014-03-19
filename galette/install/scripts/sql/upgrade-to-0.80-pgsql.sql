-- Change IP size to handle ipv6 address
ALTER TABLE galette_logs ALTER ip_log TYPE varchar(46);

