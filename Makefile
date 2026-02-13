# Usage:
# make compile PLUGIN_VERSION=3.1.3

.PHONY: compile
compile:
	bash ./generate-white-label.sh "$(PLUGIN_VERSION)"
