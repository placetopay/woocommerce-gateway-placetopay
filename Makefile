# Usage:
# make compile PLUGIN_VERSION=3.1.1

.PHONY: compile
compile:
	bash ./generate-white-label.sh "$(PLUGIN_VERSION)"
