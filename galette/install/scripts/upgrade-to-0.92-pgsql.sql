--change account creation mail
UPDATE galette_texts SET tbody = 'Hello,\r\n\r\nYou\'ve just been subscribed on the members management system of {ASSO_NAME}.\r\n\r\nIt is now possible to follow in real time the state of your subscription and to update your preferences from the web interface.\r\n\r\nPlease login at this address to set your new password :\r\n{CHG_PWD_URI}\r\n\r\nUsername: {LOGIN}\r\nThe above link will be valid until {LINK_VALIDITY}.\r\n\r\nSee you soon!\r\n\r\n(this mail was sent automatically)' WHERE tref = 'sub' AND tlang = 'en_US';

UPDATE galette_texts SET tbody = 'Bonjour,\r\n\r\nVous venez d\'adhérer à {ASSO_NAME}.\r\n\r\nVous pouvez désormais suivre en temps réel l\'état de vos souscriptions et mettre à jour vos coordonnées depuis l\'interface web.\r\n\r\nConnectez vous à cette adresse pour valider le nouveau mot de passe :\r\n{CHG_PWD_URI}\r\n\r\nIdentifiant : {LOGIN}\r\nLe lien ci-dessus sera valide jusqu\'au {LINK_VALIDITY}.\r\n\r\nA bientôt!\r\n\r\n(Ce courriel est un envoi automatique)' WHERE tref = 'sub' AND tlang = 'fr_FR';

UPDATE galette_database SET version = 0.92;
