.SUFFIXES = .mo .po
INSTALLDIR = .
LANGUAGES = en_US fr_FR@euro es_ES@euro
PACKAGE = galette
PHP_SOURCES = ../index.php \
../self_adherent.php \
../self_adherent.php \
../mailing_adherents.php \
../log.php \
../self_contribution.php \
../index.php \
../lostpasswd.php \
../footer.php \
../includes/session.inc.php \
../includes/i18n.inc.php \
../configurer_fiches.php \
../preferences.php \
../voir_adherent.php \
../gestion_contributions.php \
../ajouter_contribution.php \
../gestion_adherents.php \
../etiquettes_adherents.php \
../install/index.php \
../ajouter_adherent.php

all : messages.po lang
	@for l in ${LANGUAGES}; \
	  do \
	  echo "Mise à jour de $$l.po"; \
	  msgmerge -U $$l.po messages.po >/dev/null 2>&1; \
	  mkdir -p ${INSTALLDIR}/$$l/LC_MESSAGES; \
	  echo "  formattage de ${INSTALLDIR}/$$l/LC_MESSAGES/${PACKAGE}.mo."; \
	  msgfmt $$l.po -o ${INSTALLDIR}/$$l/LC_MESSAGES/${PACKAGE}.mo; \
	done

lang :
	make_lang_l12n.py en_US.po lang_english.php
	make_lang_l12n.py fr_FR@euro.po lang_french.php
	make_lang_l12n.py es_ES@euro.po lang_spanish.php

messages.po : ${PHP_SOURCES}
	xgettext -n ${PHP_SOURCES} >/dev/null 2>&1
