.SUFFIXES = .mo .po
INSTALLDIR = .
LANGUAGES = en_US fr_FR.utf8
PACKAGE = galette
MKLANG = ./make_lang_l12n.py
PHP_SOURCES = $(shell find ../ -maxdepth 1 -name \*.php) \
              $(shell find ../lib/Galette -name \*.php) \
              $(shell find ../includes -maxdepth 1 -name \*.php) \
              $(shell find ../includes/fields_defs -name \*.php) \
              $(shell find ../install -name \*.php) \
              $(shell find ../public -name \*.php) \
              $(shell find ../templates -name \*.tpl)


all : messages.po lang
	@echo "Génération des fichiers *.po :"
	@for l in ${LANGUAGES}; do \
	  echo -n "  Mise à jour de $$l.po"; \
	  msgmerge -U $$l.po messages.po >/dev/null ; \
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
	@${MKLANG} fr_FR.utf8.po lang_french.php.new
	@if diff -qI 'This file was automatically generated.*' lang_french.php lang_french.php.new > /dev/null; then \
	  echo "    lang_french.php non mis à jour."; \
	  rm lang_french.php.new; \
	else \
	  echo "    lang_french.php mis à jour."; \
	  mv lang_french.php.new lang_french.php; \
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
