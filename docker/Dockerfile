FROM php:cli

RUN apt-get update
RUN apt-get upgrade -y
RUN apt-get autoremove -y

RUN apt-get install -y wget zip unzip tar gzip

ADD . /src
ADD ./tools /tools
#ADD tools/getcomposer.sh /getcomposer.sh
RUN /tools/getcomposer.sh

VOLUME /src/google-drive

CMD ["php", "/src/backuptogoogledrive.php"]