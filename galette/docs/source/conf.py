# Configuration file for the Sphinx documentation builder.
#
# This file only contains a selection of the most common options. For a full
# list see the documentation:
# https://www.sphinx-doc.org/en/master/usage/configuration.html

# -- Path setup --------------------------------------------------------------

import subprocess
subprocess.call('doxygen', shell=True)

# -- Project information -----------------------------------------------------

project = 'Galette API'
copyright = '2023, Johan Cwiklinski'
author = 'Johan Cwiklinski'

# The full version, including alpha/beta/rc tags
release = '1.0.0rc3'


# -- General configuration ---------------------------------------------------

root_doc = 'contents'
html_extra_path = ['../apidocs/html']
