# Didaché — Etapa A: marca

Branch `feat/marca-didache` (local, ainda sem commit). Sem mudança de comportamento: só nome, cores, fontes, ícones e textos que falam do produto.

## O que mudou

| Área               | Mudança                                                                                                                                                                                  | Arquivos                                                                                   |
| ------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| Marca num lugar só | Nome, assinatura e cores do navegador                                                                                                                                                    | `config/brand.php`, `resources/js/lib/brand.ts`                                            |
| Símbolo            | Reconstruído à mão em SVG a partir da referência aprovada (livro com folhas). Versões em cores, para fundo escuro e de uma cor                                                           | `public/brand/simbolo*.svg`                                                                |
| Ícones             | Ícone do app no verde da marca, versão maskable, apple-touch, favicon simplificado sem nervura (SVG e ICO), selo das notificações                                                        | `public/icons/*`, `public/favicon.*`, `public/apple-touch-icon.png`                        |
| Prévia de link     | Imagem Open Graph 1200×630 e metatags `og:site_name`/`og:image`                                                                                                                          | `public/brand/og-didache.png`, `resources/views/app.blade.php`                             |
| Cores              | Tokens claro e escuro a partir das cores medidas na imagem aprovada; novo token `terra`. Contraste conferido: todos os pares de texto ≥ 5,5:1 (AA) e a borda dos campos subiu para ≈ 3:1 | `resources/css/app.css`                                                                    |
| Tipografia         | Lora no lugar de Literata (títulos e leitura); Inter continua na interface                                                                                                               | `resources/css/app.css`, `package.json`                                                    |
| Nome               | "EBD" vira "Didaché" onde é o nome do produto: logo, título das abas, PWA, login, instalação, ajuda, e-mail de senha, mensagem do link pessoal, player de áudio e servidor MCP           | ver `git diff`                                                                             |
| Abertura e PWA     | Splash, fundo da página, `manifest.webmanifest` e cache do service worker (`didache-v5`, para os aparelhos trocarem os ícones)                                                           | `resources/views/partials/splash.blade.php`, `public/manifest.webmanifest`, `public/sw.js` |
| Geração            | Script que refaz os PNGs a partir dos SVGs (precisa do Playwright, como no laboratório)                                                                                                  | `docs/brand/gerar-assets.mjs`                                                              |

**"EBD" que ficou de propósito:** onde a palavra é o programa Escola Bíblica Dominical ("Sem EBD", "Amanhã tem EBD!", "Gestão da EBD", "próxima EBD"). Esses textos passam a usar o nome do programa na etapa de programas.

## Verificação

- **Testes:** 392 (391 passam, 1 pulado, o mesmo de sempre). A única expectativa alterada foi o nome do servidor MCP (`EBD` → `Didaché`), que é só informativo; o conector continua na mesma URL.
- **Checagens:** Larastan, Pint, `tsc`, `vp check` e build passam.
- **Navegador**, no laboratório: login, início, lição e "Minha semana" no celular (tema claro e escuro) e a gestão no desktop. Capturas em `docs/didache/evidencias/etapa-a-*.png`.

## Pendências

- **`APP_NAME` em produção** (Railway) ainda deve ser "EBD". O app passou a usar `config/brand.php`, então isso não aparece para ninguém; serve só como lembrete para quem configurar e-mails (`MAIL_FROM_NAME`).
- **Ajuste fino do símbolo:** a curva das folhas e a espessura das páginas. A reconstrução é fiel, mas não idêntica ao bitmap.
- **App instalado:** o atalho no celular dos alunos atuais muda de nome e ícone; em alguns aparelhos só ao reinstalar. Vale avisar na igreja.
