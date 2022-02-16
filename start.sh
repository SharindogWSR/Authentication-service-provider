#!/usr/bin/bash
docker build -t nttek/auth . # tag, лучше везде указать один, чтобы не запутаться, например firstver
docker run -dp 8080:80 --name nttek_auth nttek/auth # 8080 - внешний, внутри слушает 80
