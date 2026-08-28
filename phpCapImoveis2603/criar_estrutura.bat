@echo off
echo =============================================
echo  CRIANDO ESTRUTURA DO SISTEMA DE CAPTAÇÃO
echo =============================================
mkdir "sistema_captacao_imoveis"
cd "sistema_captacao_imoveis"

mkdir "includes"
mkdir "pages"
mkdir "pages\imoveis"
mkdir "pages\proprietarios"
mkdir "pages\visitas"
mkdir "pages\despesas"
mkdir "pages\map"
mkdir "uploads"
mkdir "uploads\fotos_imoveis"
mkdir "css"
mkdir "js"

type nul > conn_cap.php
type nul > index.php
type nul > logout.php
type nul > dashboard.php
type nul > setup.php
type nul > includes\header.php
type nul > includes\footer.php
type nul > includes\auth.php
type nul > includes\functions.php
type nul > pages\imoveis\list.php
type nul > pages\imoveis\form.php
type nul > pages\imoveis\view.php
type nul > pages\imoveis\delete.php
type nul > pages\proprietarios\list.php
type nul > pages\proprietarios\form.php
type nul > pages\visitas\list.php
type nul > pages\visitas\form.php
type nul > pages\despesas\list.php
type nul > pages\despesas\form.php
type nul > pages\map\index.php
type nul > css\style.css
type nul > js\script.js

echo.
echo ✅ Estrutura criada com sucesso!
echo.
echo Agora abra cada arquivo e cole o código que vou mostrar abaixo.
pause