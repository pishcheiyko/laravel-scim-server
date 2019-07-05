#!/bin/bash -e

# export the .env variables
ENV_FILE="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )/.env"
[ -f $ENV_FILE ] && . $ENV_FILE

PROJECT_NAME=${COMPOSE_PROJECT_NAME:-laravel-scim-server}
WORK_DIR="${WORKDIR:-/project}"

################################

PHP_CONTAINER="${PROJECT_NAME}_php_1"

CURRENT_DIR="${PWD}"
PROJECT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
CODE_DIRS="${WORK_DIR}/src ${WORK_DIR}/routes"

NO_COLOR='\033[0m'
ERROR_COLOR='\033[1;93;41m'
GREEN_COLOR='\033[0;32m'
LIGHT_BLUE_COLOR='\033[0;94m'
YELLOW_COLOR='\033[0;33m'

SCRIPT_ARGS=''
script_args_counter=1
for argval in "$@"
do
    if  [[ script_args_counter -gt 1  ]]; then
        SCRIPT_ARGS="$SCRIPT_ARGS $argval"
    fi
    let script_args_counter=script_args_counter+1
done

CONTAINER_NAME="${PHP_CONTAINER}" # default

function safe_run () {
    local action="$1"

    if [[ -z "${action}" ]]; then
        echo -e "${ERROR_COLOR}The action is empty..${NO_COLOR}"
    else
        if [ "$(docker ps -aq -f status=running -f name=${CONTAINER_NAME})" ]; then
            eval $action
        else
            echo -e "${ERROR_COLOR}The container \"${CONTAINER_NAME}\" is not running..${NO_COLOR}"
        fi
    fi
}

function docker_bash ()
{
    local action="$1"

    if [[ ! -z "${action}" ]]; then
        docker exec -it --user=${USER_ID} ${CONTAINER_NAME} bash -c "${action}"
    else
        docker exec -it --user=${USER_ID} ${CONTAINER_NAME} bash
    fi
}

function php_unit ()
{
    docker_bash "${WORK_DIR}/vendor/bin/phpunit --stop-on-failure --verbose $@ ${SCRIPT_ARGS}"
}

function php_cs ()
{
    docker_bash \
        "${WORK_DIR}/vendor/bin/ecs check ${CODE_DIRS} $@ ${SCRIPT_ARGS}"
}

function security_check ()
{
    docker_bash \
        "${WORK_DIR}/security-checker.phar --version\
        && ${WORK_DIR}/security-checker.phar security:check ${WORK_DIR}/composer.lock\
        && ${WORK_DIR}/security-checker.phar security:check ${WORK_DIR}/vendor-bin/easy-coding-standard/composer.lock\
        && ${WORK_DIR}/security-checker.phar security:check ${WORK_DIR}/vendor-bin/php-parallel-lint/composer.lock\
        && ${WORK_DIR}/security-checker.phar security:check ${WORK_DIR}/vendor-bin/phpstan/composer.lock\
        && ${WORK_DIR}/security-checker.phar security:check ${WORK_DIR}/vendor-bin/phpunit/composer.lock"
}

echo -e "${LIGHT_BLUE_COLOR}
  (((((((((((((((((((((((((
  ((((((/((((((/(((/(((((((
  ((((((  ((  ( .((  ((((((
  ((((((. .(  ( .(  *((((((
  ((((((((.   (   *((((((((
  (((((((  /  ( .,  (((((((
  ((((((  ((  ( .((  ((((((
  ((((((  ((  ( .((  ((((((
  ((((((  ((  ( .((  ((((((
  ((((((/ *(((((((. (((((((
  ((((((((   */*   ((((((((
  (((((((((((((((((((((((((
  (((((((((((((((((((((((((
${YELLOW_COLOR}
    The Development Tools
${NO_COLOR}"

cd "${PROJECT_DIR}"

COMMAND="$1"

case "$COMMAND" in
    --security-check)
        safe_run 'security_check'
        ;;
    --phplint)
        safe_run \
            'docker_bash "${WORK_DIR}/vendor/bin/parallel-lint --version && ${WORK_DIR}/vendor/bin/parallel-lint ${CODE_DIRS} ${WORK_DIR}/tests"'
        ;;
    --phpcs)
        safe_run 'php_cs'
        ;;
    --phpstan)
        safe_run \
            'docker_bash "${WORK_DIR}/vendor/bin/phpstan --version && ${WORK_DIR}/vendor/bin/phpstan analyse"'
        ;;
    --phpunit)
        safe_run 'php_unit'
        ;;
    --php-bash)
        safe_run 'docker_bash' 
        ;;
    *)
        echo -e "
${GREEN_COLOR}To run the SensioLabs Security Checker, run:${NO_COLOR}
./devtools.sh --security-check

${GREEN_COLOR}To check the PHP syntax, run:${NO_COLOR}
./devtools.sh --phplint

${GREEN_COLOR}To check the PHP Code Standards syntax, run:${NO_COLOR}
./devtools.sh --phpcs

${GREEN_COLOR}To run the PHP Static Analysis Tool, run:${NO_COLOR}
./devtools.sh --phpstan

${GREEN_COLOR}To run the PHP Unit Tests, run:${NO_COLOR}
./devtools.sh --phpunit

${GREEN_COLOR}To get a Bash shell in PHP container, run:${NO_COLOR}
./devtools.sh --php-bash
"
esac

cd "${CURRENT_DIR}"

exit $?
