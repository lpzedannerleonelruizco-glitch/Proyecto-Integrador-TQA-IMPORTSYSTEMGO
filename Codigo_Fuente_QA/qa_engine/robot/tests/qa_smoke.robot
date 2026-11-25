*** Settings ***
Documentation     Smoke real del acceso público y la protección de rutas.
Library           SeleniumLibrary
Library           OperatingSystem
Suite Teardown    Close All Browsers

*** Variables ***
${BASE_URL}       http://127.0.0.1:8000
${SCREENSHOT_DIR}    ${CURDIR}${/}..${/}screenshots

*** Test Cases ***
TC-SMK-001 La página de acceso carga
    Open Browser    ${BASE_URL}/login    browser=Edge
    Wait Until Page Contains    Acceso corporativo    10s
    Set Window Size    1440    1000
    Capture Page Screenshot    ${SCREENSHOT_DIR}${/}login.png
    Page Should Contain    Acceso corporativo
    Location Should Contain    /login

TC-SMK-002 Ruta protegida requiere autenticación
    Open Browser    ${BASE_URL}/dashboard    browser=Edge
    Wait Until Location Contains    /login    10s
    Set Window Size    1440    1000
    Capture Page Screenshot    ${SCREENSHOT_DIR}${/}protected_redirect.png
    Location Should Contain    /login
