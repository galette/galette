INSTALLDIR = .
LANGUAGES = en_US fr_FR.utf8
DOMAINS = galette routes
MKLANG = ./make_lang_l12n.py
PHP_SOURCES = $(shell find ../ -maxdepth 1 -name \*.php) \
              $(shell find ../lib/Galette -name \*.php) \
              $(shell find ../includes -name \*.php) \
              $(shell find ../install -name \*.php) \
              $(shell find ../public -name \*.php) \
              $(shell find ../templates -name \*.tpl)


all : messages.po lang check
	@echo "Generating PO files:"
	@for l in ${LANGUAGES}; do \
	  for d in ${DOMAINS}; do \
	    if [ -f $${d}_$${l}.po ]; then \
	      echo -n "  Updating $${d}_$${l}.po"; \
	      msgmerge -U $${d}_$${l}.po $$d.pot >/dev/null ; \
	    else \
	      echo "  Creating of $${d}_$${l}.po"; \
	      msginit -o $${d}_$${l}.po -i $$d.pot >/dev/null ; \
	    fi; \
	    mkdir -p ${INSTALLDIR}/$${l}/LC_MESSAGES; \
	    echo "    formatting ${INSTALLDIR}/$${l}/LC_MESSAGES/$${d}.mo."; \
	    msgfmt $${d}_$${l}.po -o ${INSTALLDIR}/$${l}/LC_MESSAGES/$${d}.mo.new; \
	    if diff -qI 'PO-Revision-Date:.*' ${INSTALLDIR}/$${l}/LC_MESSAGES/$${d}.mo.new ${INSTALLDIR}/$${l}/LC_MESSAGES/$${d}.mo > /dev/null; then \
	      echo "    $${d}.mo NOT updated."; \
	      rm ${INSTALLDIR}/$${l}/LC_MESSAGES/$${d}.mo.new; \
	    else \
	      echo "    $${d}.mo UPDATED."; \
	      mv ${INSTALLDIR}/$${l}/LC_MESSAGES/$${d}.mo.new ${INSTALLDIR}/$${l}/LC_MESSAGES/$${d}.mo; \
	    fi; \
	  done \
	done


lang :
	@echo "Generating PHP lang files:"
	@for l in ${LANGUAGES}; do \
	  for d in ${DOMAINS}; do \
	    echo "    extracting $${d} $${l} strings..."; \
	    if [ ! -f $${d}_$${l}.php ]; then \
	      touch $${d}_$${l}.php; \
	    fi; \
	    ${MKLANG} $${d}_$${l}.po $${d}_$${l}.php.new $${d}; \
	    if diff -qI 'This file was automatically generated.*' $${d}_$${l}.php $${d}_$${l}.php.new > /dev/null; then \
	      echo "    $${d}_$${l}.php NOT updated."; \
	      rm $${d}_$${l}.php.new; \
	    else \
	      echo "    $${d}_$${l}.php UPDATED."; \
	      mv $${d}_$${l}.php.new $${d}_$${l}.php; \
	    fi; \
	  done \
	done

messages.po : ${PHP_SOURCES} Makefile xgettext.py
	./xgettext.py ${PHP_SOURCES}

check:
	@for NFILE in ./*.php; do \
		php -l $$NFILE; \
	done
