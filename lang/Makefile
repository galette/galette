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

all : messages.po
	@for l in ${LANGUAGES}; \
	  do \
	  echo "Mise à jour de $$l.po"; \
	  msgmerge -U $$l.po messages.po >/dev/null 2>&1; \
	  mkdir -p ${INSTALLDIR}/$$l/LC_MESSAGES; \
	  echo "  formattage de ${INSTALLDIR}/$$l/LC_MESSAGES/${PACKAGE}.mo."; \
	  msgfmt $$l.po -o ${INSTALLDIR}/$$l/LC_MESSAGES/${PACKAGE}.mo; \
	done

messages.po : ${PHP_SOURCES}
	xgettext -n --from-code=iso-8859-15 ${PHP_SOURCES} >/dev/null 2>&1
	sed "s/UTF-8/ISO-8859-15/" messages.po > messages.tmp
	mv messages.tmp messages.po
	recode UTF-8..ISO-8859-15 messages.po

