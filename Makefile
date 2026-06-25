.PHONY: all build dev watch clean install

all: build

install:
	npm ci

build: install
	npm run build

dev: install
	npm run dev

watch: install
	npm run watch

clean:
	rm -rf node_modules js/nc_ytdlp-main.js js/nc_ytdlp-main.js.map css/
