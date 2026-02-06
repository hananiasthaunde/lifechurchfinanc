# 🔧 Configuração Automática de Ambiente

## ✅ O Que Foi Implementado

O arquivo `includes/config.php` agora detecta **automaticamente** se você está rodando em:

| Ambiente | Detecção | Credenciais |
|----------|----------|-------------|
| **Localhost** | `localhost`, `127.0.0.1`, pasta `xampp` | `root` / senha vazia / `lifechurch_db` |
| **Produção** | Qualquer outro host | Credenciais do cPanel |

---

## 🚀 Como Usar

### No Localhost (XAMPP)

1. Inicie o Apache e MySQL no XAMPP
2. Crie o banco de dados `lifechurch_db` no phpMyAdmin (se não existir)
3. Importe as tabelas necessárias
4. Acesse: `http://localhost/lifechurchfinanc-main`

### Na Nuvem

1. Faça upload via FTP ou Git
2. O sistema detecta automaticamente e usa as credenciais do cPanel
3. Acesse: `https://lifechurchfinance.aplicweb.com`

---

## 🔒 Segurança

- **Localhost**: Erros detalhados para debug
- **Produção**: Erros ocultos, apenas logs

---

## 📁 Estrutura

```
includes/
├── config.php          ← Configuração inteligente (ESTE ARQUIVO)
└── config cloud.php    ← Backup antigo (pode ser removido)
```

---

## ⚠️ Importante

Se precisar alterar credenciais no futuro, edite apenas o `config.php`:

- Linhas 62-67: Credenciais localhost
- Linhas 79-84: Credenciais produção
