# LightRanks — лёгкая система рангов для PocketMine-MP

**LightRanks** — это мощный, но простой плагин для управления рангами (группами) на сервере PocketMine-MP. Плагин позволяет выдавать ранги, повышать/понижать игроков и отображать префиксы в чате.

---

## 📥 Установка

1. Скачай последнюю версию `.phar` файла со страницы [релизов](https://github.com/egor96575446445-dot/LightRanks/releases) или с [Poggit](https://poggit.pmmp.io/p/LightRanks)
2. Помести файл в папку `plugins` твоего сервера
3. Перезапусти сервер

---

## 🔧 Команды (все)

| Команда | Описание | Право |
|---------|----------|-------|
| `/setrank <игрок> <ранг>` | Выдать ранг игроку | `lightranks.admin` |
| `/removerank <игрок>` | Снять ранг с игрока | `lightranks.admin` |
| `/promote <игрок>` | Повысить игрока на 1 ранг | `lightranks.admin` |
| `/demote <игрок>` | Понизить игрока на 1 ранг | `lightranks.admin` |
| `/myrank` | Узнать свой ранг | `lightranks.command.myrank` |
| `/rankslist` | Показать список всех рангов | `lightranks.command.rankslist` |

---

## ⚙️ Конфигурация (`ranks.yml`)

```yaml
default-rank: "Guest"

ranks:
  Guest:
    prefix: "§7[Гость]"
    priority: 0
  Player:
    prefix: "§a[Игрок]"
    priority: 1
  VIP:
    prefix: "§6[§lVIP§r§6]"
    priority: 2
  Moder:
    prefix: "§b[§lМод§r§b]"
    priority: 3
  Admin:
    prefix: "§c[§lАдмин§r§c]"
    priority: 4
  Owner:
    prefix: "§4[§lВладелец§r§4]"
    priority: 5
