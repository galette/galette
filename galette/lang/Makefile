.SUFFIXES = .mo .po
INSTALLDIR = .
LANGUAGES = en_US fr_FR@euro es_ES@euro
PACKAGE = galette
MKLANG = ./make_lang_l12n.py
PHP_SOURCES = ../index.php \
../self_adherent.php \
../mailing_adherents.php \
../log.php \
../self_contribution.php \
../lostpasswd.php \
../change_passwd.php \
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
../ajouter_adherent.php \
../ajouter_transaction.php \
../editer_champ.php \
../gestion_transactions.php \
../picture.php \
../traduire_libelles.php \
../templates/default/index.tpl \
../templates/default/gestion_adherents.tpl \
../templates/default/mailing_adherents.tpl \
../templates/default/page.tpl \
../templates/default/preferences.tpl \
../templates/default/ajouter_adherent.tpl \
../templates/default/ajouter_contribution.tpl \
../templates/default/configurer_fiches.tpl \
../templates/default/gestion_contributions.tpl \
../templates/default/traduire_libelles.tpl \
../templates/default/log.tpl \
../templates/default/voir_adherent.tpl \
../templates/default/ajouter_transaction.tpl \
../templates/default/display_dynamic_fields.tpl \
../templates/default/editer_champ.tpl \
../templates/default/gestion_transactions.tpl \
../templates/default/lostpasswd.tpl \
../templates/default/change_passwd.tpl \
../templates/default/self_adherent.tpl


all : messages.po lang
	@echo "Génération des fichiers *.po :"
	@for l in ${LANGUAGES}; do \
	  echo "  Mise à jour de $$l.po"; \
	  msgmerge -U $$l.po messages.po >/dev/null 2>&1; \
	  mkdir -p ${INSTALLDIR}/$$l/LC_MESSAGES; \
	  echo "    formatage de ${INSTALLDIR}/$$l/LC_MESSAGES/${PACKAGE}.mo."; \
	  msgfmt $$l.po -o ${INSTALLDIR}/$$l/LC_MESSAGES/${PACKAGE}.mo.new; \
	  if diff -qI 'PO-Revision-Date:.*' ${INSTALLDIR}/$$l/LC_MESSAGES/${PACKAGE}.mo.new ${INSTALLDIR}/$$l/LC_MESSAGES/${PACKAGE}.mo > /dev/null; then \
	    echo "    ${PACKAGE}.mo non mis à jour."; \
	    rm ${INSTALLDIR}/$$l/LC_MESSAGES/${PACKAGE}.mo.new; \
	  else \
	    echo "    ${PACKAGE}.mo mis à jour."; \
	    mv ${INSTALLDIR}/$$l/LC_MESSAGES/${PACKAGE}.mo.new ${INSTALLDIR}/$$l/LC_MESSAGES/${PACKAGE}.mo; \
	  fi; \
	done


lang :
	@echo "Génération des fichiers lang_*.php"
	@echo "  Mise à jour de lang_english.php"
	@echo "    extraction des chaines..."
	@${MKLANG} en_US.po lang_english.php.new
	@if diff -qI 'This file was automatically generated.*' lang_english.php lang_english.php.new > /dev/null; then \
	  echo "    lang_english.php non mis à jour."; \
	  rm lang_english.php.new; \
	else \
	  echo "    lang_english.php mis à jour."; \
	  mv lang_english.php.new lang_english.php; \
	fi; 

	@echo "  Mise à jour de lang_french.php"
	@echo "    extraction des chaines..."
	@${MKLANG} fr_FR@euro.po lang_french.php.new
	@if diff -qI 'This file was automatically generated.*' lang_french.php lang_french.php.new > /dev/null; then \
	  echo "    lang_french.php non mis à jour."; \
	  rm lang_french.php.new; \
	else \
	  echo "    lang_french.php mis à jour."; \
	  mv lang_french.php.new lang_french.php; \
	fi; 

	@echo "  Mise à jour de lang_spanish.php"
	@echo "    extraction des chaines..."
	@${MKLANG} es_ES@euro.po lang_spanish.php.new

	@if diff -qI 'This file was automatically generated.*' lang_spanish.php lang_spanish.php.new > /dev/null; then \
	  echo "    lang_spanish.php non mis à jour."; \
	  rm lang_spanish.php.new; \
	else \
	  echo "    lang_spanish.php mis à jour."; \
	  mv lang_spanish.php.new lang_spanish.php; \
	fi; 

messages.po : ${PHP_SOURCES} Makefile xgettext.py
	./xgettext.py ${PHP_SOURCES}

check:
	@for FILE in ${PHP_SOURCES}; do				\
		test -f $$FILE || echo "Not found $$FILE";	\
	done 
	@for NFILE in ../*.php ../includes/*.php		\
			../templates/default/*.tpl; do		\
		for OFILE in ${PHP_SOURCES}; do			\
			if [ "$$NFILE" = "$$OFILE" ]; then	\
				unset NFILE;			\
				break;				\
			fi;					\
		done;						\
		test -z "$$NFILE" || echo "Missing $$NFILE";	\
	done
