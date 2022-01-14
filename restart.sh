#!/usr/bin/bash
docker container rm -f nttek_auth
docker build -t nttek/auth .
docker run -dp 8080:80 --name nttek_auth nttek/auth