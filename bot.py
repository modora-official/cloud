from telegram import Update
from telegram.ext import Application, CommandHandler, MessageHandler, ContextTypes, filters

TOKEN = "7323628077:AAH0xnx4WDR9xzaXxPFTygL_kT5VBwTvNHw"

async def start(update: Update, context: ContextTypes.DEFAULT_TYPE):
    await update.message.reply_text("🚀 Modora Bot Aktif!")

async def ping(update: Update, context: ContextTypes.DEFAULT_TYPE):
    await update.message.reply_text("🏓 Pong!")

async def id(update: Update, context: ContextTypes.DEFAULT_TYPE):
    await update.message.reply_text(
        f"User ID: {update.effective_user.id}\n"
        f"Chat ID: {update.effective_chat.id}"
    )

async def echo(update: Update, context: ContextTypes.DEFAULT_TYPE):
    await update.message.reply_text(update.message.text)

app = Application.builder().token(TOKEN).build()

app.add_handler(CommandHandler("start", start))
app.add_handler(CommandHandler("ping", ping))
app.add_handler(CommandHandler("id", id))
app.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, echo))

print("Bot berjalan...")
app.run_polling()
