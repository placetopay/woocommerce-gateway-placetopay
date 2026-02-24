# Usage:
# make compile PLUGIN_VERSION=3.2.0

.PHONY: compile
compile:
	bash ./generate-white-label.sh "$(PLUGIN_VERSION)"
