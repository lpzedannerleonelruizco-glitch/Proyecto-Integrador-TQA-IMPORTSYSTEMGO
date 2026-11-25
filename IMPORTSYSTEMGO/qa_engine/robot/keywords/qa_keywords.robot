*** Settings ***
Documentation     Keywords personales del QA Center.
Library           SeleniumLibrary

*** Keywords ***
Abrir página QA
    [Arguments]    ${url}
    Open Browser    ${url}    browser=Edge
    Set Selenium Implicit Wait    2 seconds

Cerrar navegador QA
    Close All Browsers
