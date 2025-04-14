from flask import Flask, request, jsonify
from flask_cors import CORS
import requests
from requests.auth import HTTPBasicAuth
import os
from openai import OpenAI
import json
from dotenv import load_dotenv
import re

# -------------------------------------------------------
# Ce fichier a été créé avec l'aide du Chat GPT et Stack Overflow
# -------------------------------------------------------

# -------------------------------------------------------
# LOGIQUE DU FICHIER :
# Cette API Flask reçoit un message d'utilisateur, utilise GPT pour identifier
# une tâche parmi 5 possibles, génère une requête formatée, et transmet cette
# requête à un script PHP (chatbot.php) via une requête POST avec authentification.
# Le résultat retourné par chatbot.php est ensuite renvoyé au client.
# -------------------------------------------------------

# S'assurer que votre clé API OpenAI est bien définie dans vos variables d'environnement.
load_dotenv()
client = OpenAI(
    # C'est la valeur par défaut et peut être omise
    api_key=os.environ.get("OPENAI_API_KEY"),
)

def clean_json_string(json_string):
    """
    Nettoie une chaîne JSON en supprimant les virgules finales non valides.

    Paramètre :
        json_string (str) : Chaîne JSON brute à nettoyer.

    Retour :
        str : Chaîne JSON nettoyée, sans virgules finales invalides.
    """
    # Supprimer les virgules avant les accolades ou crochets fermants
    cleaned_string = re.sub(r',\s*}', '}', json_string)
    cleaned_string = re.sub(r',\s*]', ']', cleaned_string)
    return cleaned_string

def ai_decision_maker(user_message):
    """
    Utilise GPT pour identifier une tâche et générer une requête formatée.
    Le prompt demande à GPT de retourner un objet JSON avec les clés :
    'task', 'formattedQuery' et 'informations'.

    Paramètre :
        user_message (str) : Message envoyé par l'utilisateur.

    Retour :
        dict : Objet JSON contenant la tâche, la requête formatée, et des informations détaillées.
    """
    prompt = (
    # Noms des tâches
    f"There are 5 tasks to consider: 'country information', 'finding places', 'calculating distance between 2 or more points', 'weather details' and 'place details'.\n"

    # Requêtes formatées
    f"There are also 5 formatted queries to consider:\n"
    f"1. 'tell me about (country name in the user message)'\n"
    f"2. 'find (type of place to search, e.g., hospital, school, etc.) in (city name in the user message)'\n. Note: the type of place should be a always a singular noun.\n"
    f"3. 'route from (starting point specified in the user message, e.g., city name, name of a place, etc.) to (ending point specified in the user message, e.g., city name, name of a place, etc.). Note: the type of place should be a always a singular noun.'\n"
    f"4. 'weather in (city name specified in the user message)'\n"
    f"5. 'details concerning (type of place to search, e.g., hospital, school, etc.) in (city name specified in the user message). Note: the type of place should be a always a singular noun.'\n"

    # Informations pour chaque tâche
    f"For each task, include the following additional details in the 'informations' field:\n"
    f" - For 'country information': list major attractions, describe the history and culture, explain the government and economy, and mention local cuisine.\n"
    f" - For 'finding places': provide a list of 5 best places based on reviews and ratings, and include details about additional services offered (include reviews/ratings and services details).\n"
    f" - For 'calculating distance between 2 or more points': mention available modes of transport, note any notable landmarks, and list stops or attractions along the route.\n"
    f" - For 'weather details': include additional metrics such as air quality, sunrise and sunset times, etc.\n"
    f" - For 'place details': list 5 places within the specified city and include their social media contacts or website links.\n\n"

    # Requête GPT
    f"Based on the user message: '{user_message}', determine the appropriate task, create the correct formatted query, and provide the detailed informations in french.\n"
    f"Return only a JSON valid object with exactly three keys: 'task', 'formattedQuery' and 'informations'. The values of these keys need to be text strings.\n"
    f"Ensure the JSON is valid, well-structured and does not contain any unterminated strings or invalid characters.\n"
    )
    
    
    try:
        response = client.responses.create(
            model="gpt-3.5-turbo",  # ou un autre modèle de votre choix
            instructions="system",
            input=prompt,
            max_output_tokens=200,
            temperature=0.3
        )
        output_text = response.output_text.strip()
        cleaned_output = clean_json_string(output_text)
        # Essayer d'analyser la sortie de GPT en JSON
        json_response = json.loads(cleaned_output)
    except Exception as e:
        # Utiliser une réponse par défaut en cas d'erreur
        json_response = {
            "task": "problem",
            "formattedQuery": cleaned_output,
            "debug_error": str(e),
        }
    return json_response


app = Flask(__name__)
CORS(app)

@app.route('/decision', methods=['POST'])
def decision_endpoint():
    """
    Point de terminaison Flask pour recevoir un message utilisateur,
    déterminer la tâche via GPT, transmettre la requête à chatbot.php
    avec authentification HTTP, et retourner le résultat au client.

    Retour :
        JSON : Réponse retournée par chatbot.php ou erreur.
    """
    try:
        data = request.get_json(force=True)
        user_message = data.get("message", "")
        
        # Construire la décision (par exemple, une requête météo)
        decision = ai_decision_maker(user_message)
        
        # Transmettre la décision à chatbot.php en utilisant l'authentification HTTP basique
        url = "http://127.0.0.1/chatbot.php"
        user = 'myuser'
        password = 'password'
        execution_response = requests.post(url, 
                                           json=decision,
                                           auth=HTTPBasicAuth(user, password))
        
        # Analyser la réponse JSON depuis chatbot.php
        execution_result = execution_response.json()
    except Exception as e:
        execution_result = {"error": str(e)}
    
    return jsonify(execution_result)

if __name__ == '__main__':
    # Remarque : pour une communication interne, le port 5000 convient.
    app.run(debug=False, host='0.0.0.0', port=5000)
