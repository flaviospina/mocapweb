# Envio de e-mail • MocapWeb • Aviso de conta de acesso (n8n)

Automação em n8n que avisa, por e-mail, os professores e professoras da lista
recebida que uma conta de acesso ao sistema **MocapWeb** foi criada para eles.
O e-mail usa **o mesmo template do "Aviso CECAPE"** (workflow `Gmails Isolado`):
cabeçalho com os logos SEEDUC/CECAPE, cartão azul com os dados, aviso de
mensagem automática e rodapé.

## O que está nesta pasta

| Arquivo | Para que serve |
|---|---|
| `mocapweb-aviso-conta-acesso.json` | **Workflow principal.** Gatilho manual → nó *Code* com a lista de destinatários → 1 nó *Gmail* que envia para cada pessoa. Recomendado. |
| `mocapweb-aviso-conta-acesso-isolado.json` | Mesmo envio no formato do `Gmails Isolado`: 10 nós *Gmail* soltos, um por pessoa, com o HTML já preenchido. Use se preferir disparar um a um. |
| `dados/professores-mocapweb.csv` | Lista completa das 21 pessoas (com e sem e-mail), sexo inferido, tratamento e observações. Separador `;`. |
| `templates/mocapweb-conta-acesso.html` | Template do e-mail com marcadores `{{NOME}}`, `{{EMAIL}}` etc., para referência ou reaproveitamento. |

## Quem recebe

Somente quem tem e-mail na relação: **10 pessoas** (linhas 1, 2, 5, 8, 9, 10, 11, 16, 17 e 18).
As outras 11 ficam no CSV com `tem_email = NÃO` e não entram no disparo.

## Tratamento por sexo (inferido pelo nome)

O e-mail usa "Professora … / cadastrada" ou "Professor … / cadastrado".
Dois casos merecem confirmação antes do envio:

- **Daniele Fernando da Silva** — marcado como **M** ("Fernando" indica masculino, embora "Daniele" seja normalmente feminino).
- **Diyony Uiara Sampaio Marsilli** — marcado como **F** ("Uiara" é nome feminino; "Diyony" é incomum).

Também vale notar que **Shirley Monteiro Maciel** está com o e-mail do setor
(`educacaoespecialalcina@…`), não um e-mail pessoal.

Para corrigir, altere o campo `genero` ("F" ou "M") no nó **Lista de professores**
(workflow principal) ou troque as palavras no nó da pessoa (workflow isolado).

## Senha provisória

O nó **Lista de professores** tem o campo `senha_provisoria` vazio para todos.
Se ficar vazio, o e-mail mostra "será informada pela equipe de TI do CECAPE".
Se preencher, a senha aparece no cartão do e-mail. No workflow isolado a senha
precisa ser editada diretamente no HTML de cada nó.

## Como importar e disparar no n8n

1. No n8n, **Workflows → Import from File** e escolha `mocapweb-aviso-conta-acesso.json`.
2. Abra o nó **Gmail - Enviar MocapWeb** e confirme a credencial
   **Gmail account 2** (a mesma do `Gmails Isolado`, remetente `no-reply.cecape@scseduca.com.br`).
   Se a instância não reconhecer o ID, basta selecionar a credencial na lista.
3. Abra o nó **Lista de professores**, revise nomes, sexo e senhas.
4. **Teste primeiro**: troque temporariamente o `email` de uma pessoa pelo seu
   e execute o workflow; confira o e-mail recebido.
5. Volte o e-mail original e clique em **Execute workflow**. O nó Gmail envia
   um e-mail por item (10 no total). O envio fica registrado na aba
   *Executions* do n8n.

Assunto enviado: `Aviso CECAPE • MocapWeb • Sua conta de acesso foi criada`.

## Conteúdo do e-mail

- Saudação com tratamento e nome completo.
- Informação de que a conta de acesso ao MocapWeb foi criada.
- Cartão com: sistema, endereço (`https://cecapescs.com.br/mocapweb/`), usuário
  (o próprio e-mail), senha provisória e unidade escolar.
- Descrição curta do MocapWeb: captura de movimentos corporais e faciais direto
  no navegador, apenas com webcam; até 6 pessoas no modo padrão (MediaPipe) e até
  20 no modo ampliado (YOLO-pose); comparação com vídeo de referência; exportação
  CSV, JSON e BVH.
- Link da apresentação/tutorial (`apresentacao.html`).
- Aviso de mensagem automática e rodapé institucional.

## Pontos verificados em 22/09/2026 que merecem atenção

- `https://cecapescs.com.br/logos/logo-cecape.png` está respondendo **404**.
  O template do CECAPE (e este) usa essa imagem no cabeçalho; até o arquivo ser
  restaurado, o logo do CECAPE aparece quebrado no e-mail. O logo da SEEDUC está OK.
  Há uma cópia em `https://cecapescs.com.br/proj/cecape-palavras/assets/img/Logo-CECAPE.png`
  que pode ser copiada para `/logos/logo-cecape.png` pelo gerenciador de arquivos do cPanel.
- `https://cecapescs.com.br/mocapweb/login.php` está respondendo **HTTP 500**.
  Convém corrigir antes do disparo, pois o e-mail direciona as pessoas para o login.
